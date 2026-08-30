<?php
namespace App\Services;

use App\Models\ApiConnector;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\ContactList;
use App\Models\Customer;
use App\Models\Lead;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

// Campaign sending (Directive S3.P).
//
// Per-recipient outcomes are recorded from REAL send attempts — the
// campaign report counts what actually happened, never what was intended.
// A channel with no configured transport reports every recipient as
// `skipped` with a CONTRACT REQUIRED reason instead of silently claiming
// success, which is the failure mode the directive explicitly forbids.
class CampaignDispatcher
{
    public function __construct(private ApiConnectorService $connectors)
    {
    }

    /**
     * Materialises the recipient list from the campaign's contact list.
     * Contacts with no usable destination for the channel are recorded as
     * `skipped` with the reason, so the shortfall is visible in the report.
     */
    public function buildRecipients(Campaign $campaign): int
    {
        if (!$campaign->contact_list_id) {
            return 0;
        }

        $list = ContactList::where('tenant_id', $campaign->tenant_id)->find($campaign->contact_list_id);
        if (!$list) {
            return 0;
        }

        $created = 0;

        foreach ($list->members()->with('customer', 'lead')->cursor() as $member) {
            $contact = $member->customer ?? $member->lead;
            if (!$contact) {
                continue;
            }

            $destination = $this->destinationFor($campaign->channel, $contact);

            CampaignRecipient::updateOrCreate(
                [
                    'campaign_id' => $campaign->id,
                    'customer_id' => $member->customer_id,
                    'lead_id' => $member->lead_id,
                ],
                [
                    'destination' => $destination ?? '',
                    'status' => $destination ? 'pending' : 'skipped',
                    'failure_reason' => $destination
                        ? null
                        : "No {$campaign->channel} destination on record for this contact",
                ],
            );

            $created++;
        }

        return $created;
    }

    /**
     * Sends every pending recipient and records each real outcome.
     *
     * @return array{sent:int, failed:int, skipped:int}
     */
    public function send(Campaign $campaign): array
    {
        $transport = $this->resolveTransport($campaign);

        $campaign->update(['status' => 'sending', 'started_at' => now()]);

        $sent = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($campaign->recipients()->where('status', 'pending')->cursor() as $recipient) {
            if (!$transport['available']) {
                $recipient->update([
                    'status' => 'skipped',
                    'failure_reason' => $transport['reason'],
                ]);
                $skipped++;
                continue;
            }

            try {
                $this->deliver($campaign, $recipient, $transport);
                $recipient->update(['status' => 'sent', 'sent_at' => now(), 'failure_reason' => null]);
                $sent++;
            } catch (\Throwable $e) {
                Log::warning('Campaign send failed', [
                    'campaign_id' => $campaign->id,
                    'recipient_id' => $recipient->id,
                    'error' => $e->getMessage(),
                ]);
                $recipient->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);
                $failed++;
            }
        }

        $campaign->update([
            // A run where nothing could be delivered is not "sent".
            'status' => ($sent === 0 && ($failed > 0 || $skipped > 0)) ? 'failed' : 'sent',
            'completed_at' => now(),
        ]);

        return ['sent' => $sent, 'failed' => $failed, 'skipped' => $skipped];
    }

    /**
     * Decides how this channel can actually be delivered right now.
     * Email uses Laravel's configured mailer; SMS and WhatsApp require an
     * operator-configured connector from the Phase 8 Integration Manager.
     */
    private function resolveTransport(Campaign $campaign): array
    {
        if ($campaign->channel === 'email') {
            return ['available' => true, 'type' => 'mail', 'connector' => null, 'reason' => null];
        }

        $connector = ApiConnector::where('tenant_id', $campaign->tenant_id)
            ->where('category', $campaign->channel)
            ->where('is_active', true)
            ->first();

        if (!$connector || !$connector->endpointFor('send')) {
            return [
                'available' => false,
                'type' => null,
                'connector' => null,
                'reason' => "CONTRACT REQUIRED: no active {$campaign->channel} provider with a mapped "
                    . "'send' endpoint is configured. Add one under Integrations before sending.",
            ];
        }

        return ['available' => true, 'type' => 'connector', 'connector' => $connector, 'reason' => null];
    }

    private function deliver(Campaign $campaign, CampaignRecipient $recipient, array $transport): void
    {
        if ($transport['type'] === 'mail') {
            Mail::raw($campaign->body, function ($mail) use ($recipient, $campaign) {
                $mail->to($recipient->destination)->subject($campaign->subject ?? $campaign->name);
            });

            return;
        }

        // Operator-configured SMS / WhatsApp provider.
        $this->connectors->execute($transport['connector'], 'send', [
            'to' => $recipient->destination,
            'message' => $campaign->body,
            'subject' => $campaign->subject,
        ], $campaign->created_by);
    }

    private function destinationFor(string $channel, Customer|Lead $contact): ?string
    {
        return match ($channel) {
            'email' => $contact->email ?: null,
            'sms', 'whatsapp' => $contact->phone ?: null,
            default => null,
        };
    }
}
