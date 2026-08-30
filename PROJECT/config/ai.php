<?php

// Provider-independent AI configuration. No provider is hardcoded into
// application logic — services must resolve behavior through the
// AiProvider model, keyed by this list of supported provider identifiers.
// API credentials live only in env vars / the AiProvider table (encrypted
// at rest by the model's cast) and are never sent to the frontend.
return [
    'supported_providers' => ['openai', 'anthropic', 'gemini'],

    'default_provider' => env('AI_DEFAULT_PROVIDER'),

    // Hard ceiling applied regardless of per-provider config, to prevent
    // runaway spend if a tenant misconfigures limits.
    'global_monthly_cost_ceiling_usd' => env('AI_GLOBAL_MONTHLY_COST_CEILING_USD', 500),
];
