<?php
namespace App\Services\Ai;

use App\Models\AiProvider;

// Contract every AI vendor adapter implements. Application code depends on
// this interface only — adding a new vendor means adding an adapter, never
// touching business logic (Directive S4: provider-independent, no single
// provider hardcoded anywhere).
interface AiProviderAdapter
{
    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @throws AiProviderException
     */
    public function complete(AiProvider $provider, array $messages, ?string $system, array $options = []): AiCompletionResult;
}
