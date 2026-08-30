<?php
namespace App\Http\Controllers;
use App\Models\Customer;
use App\Models\Note;
use App\Models\Document;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

// Aggregates every recorded touchpoint for a customer (communications,
// notes, documents, tasks, bookings) into a single chronological feed —
// the "complete customer timeline" required by the CRM spec. Built from
// real rows only; there is no synthetic/mock timeline data.
class CustomerTimelineController extends Controller
{
    public function show(Request $request, $customerId)
    {
        $customer = Customer::where('tenant_id', $request->user->tenant_id)->findOrFail($customerId);

        $events = new Collection();

        foreach ($customer->communications as $c) {
            $events->push([
                'type' => 'communication',
                'sub_type' => $c->type,
                'title' => $c->subject,
                'occurred_at' => $c->sent_at ?? $c->created_at,
                'ref_id' => $c->id,
            ]);
        }

        foreach (Note::where('tenant_id', $customer->tenant_id)
            ->where('notable_type', Customer::class)
            ->where('notable_id', $customer->id)
            ->get() as $n) {
            $events->push([
                'type' => 'note',
                'sub_type' => null,
                'title' => str($n->body)->limit(80)->toString(),
                'occurred_at' => $n->created_at,
                'ref_id' => $n->id,
            ]);
        }

        foreach (Document::where('tenant_id', $customer->tenant_id)
            ->where('documentable_type', Customer::class)
            ->where('documentable_id', $customer->id)
            ->get() as $d) {
            $events->push([
                'type' => 'document',
                'sub_type' => $d->category,
                'title' => $d->file_name,
                'occurred_at' => $d->created_at,
                'ref_id' => $d->id,
            ]);
        }

        foreach (Task::where('tenant_id', $customer->tenant_id)
            ->where('related_entity_type', Customer::class)
            ->where('related_entity_id', $customer->id)
            ->get() as $t) {
            $events->push([
                'type' => 'task',
                'sub_type' => $t->status,
                'title' => $t->title,
                'occurred_at' => $t->created_at,
                'ref_id' => $t->id,
            ]);
        }

        foreach ($customer->bookings as $b) {
            $events->push([
                'type' => 'booking',
                'sub_type' => $b->status ?? null,
                'title' => 'Booking #' . $b->id,
                'occurred_at' => $b->created_at,
                'ref_id' => $b->id,
            ]);
        }

        $events = $events->sortByDesc('occurred_at')->values();

        return response()->json(['data' => $events]);
    }
}
