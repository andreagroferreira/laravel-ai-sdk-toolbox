<?php

declare(strict_types=1);

return [

    'skills' => [

        /*
         * Named paths scanned for skills (each subdirectory with a SKILL.md).
         * The key is the "source" name used by the trust configuration below.
         * "local" holds first-party skills; "installed" receives third-party
         * skills installed via ai:skill-install.
         */
        'paths' => [
            'local' => resource_path('ai/skills'),
            'installed' => storage_path('app/ai/skills'),
        ],

        /*
         * Trust model. Sources not listed here get the "default" trust level.
         * Levels: "trusted", "untrusted". Per-skill trust recorded in the
         * ai-skills.lock file takes precedence over the source level.
         */
        'trust' => [
            'default' => 'untrusted',
            'sources' => [
                'local' => 'trusted',
                'installed' => 'untrusted',
            ],
        ],

        /*
         * Composite skills (skills with a PHP provider class) are only loaded
         * from sources with these trust levels.
         */
        'composite' => [
            'allow_from' => ['trusted'],
        ],
    ],

    'knowledge' => [

        /*
         * The vector store driver. "pgvector" uses the knowledge_chunks
         * table with the native Laravel vector column (PostgreSQL only);
         * "qdrant" talks to a Qdrant server over its HTTP API.
         */
        'store' => env('AI_TOOLBOX_KB_STORE', 'pgvector'),

        'stores' => [
            'qdrant' => [
                'url' => env('QDRANT_URL', 'http://localhost:6333'),
                'api_key' => env('QDRANT_API_KEY'),
                'collection' => env('QDRANT_COLLECTION', 'knowledge_chunks'),
            ],
        ],

        'embeddings' => [
            'provider' => env('AI_TOOLBOX_KB_EMBEDDINGS_PROVIDER'),
            'model' => env('AI_TOOLBOX_KB_EMBEDDINGS_MODEL'),
            'dimensions' => (int) env('AI_TOOLBOX_KB_EMBEDDINGS_DIMENSIONS', 1536),
            'cache' => true,
        ],

        'chunking' => [
            'size' => 1500,
            'overlap' => 200,
        ],

        'sync' => [
            'queue' => env('AI_TOOLBOX_KB_QUEUE', 'default'),
            'batch' => 100,
        ],

        /*
         * Knowledge sources keyed by name. Each source maps a filesystem
         * disk + path prefix to an isolated namespace.
         *
         * Example:
         * 'docs' => ['disk' => 's3', 'path' => 'kb/', 'namespace' => 'docs',
         *            'chunker' => 'markdown', 'extensions' => ['md', 'txt', 'html', 'pdf']],
         */
        'sources' => [],
    ],

    'plugins' => [

        /*
         * Where third-party plugins are installed (ai:plugin-install).
         */
        'path' => storage_path('app/ai/plugins'),
    ],

    'http' => [
        /*
         * The HTTP management layer is opt-in. Routes only respond when this
         * is enabled (the middleware answers 404 otherwise).
         */
        'enabled' => env('AI_TOOLBOX_HTTP_ENABLED', false),

        'prefix' => env('AI_TOOLBOX_HTTP_PREFIX', 'ai-toolbox'),

        /*
         * Middleware applied to every management route. Authorization itself
         * is decided by the AiSdkToolbox::authorize() callback. Use ['api',
         * 'auth:sanctum'] for token-based APIs.
         */
        'middleware' => ['web'],

        /*
         * Rate limit for the install endpoint (git clones are expensive).
         */
        'throttle' => '10,1',

        /*
         * Allow force-installing skills that the security scan BLOCKED over
         * HTTP. Off by default: blocked skills can only be forced via CLI.
         */
        'allow_force' => env('AI_TOOLBOX_HTTP_ALLOW_FORCE', false),
    ],

    'cli_tools' => [

        'enabled' => env('AI_TOOLBOX_CLI_TOOLS_ENABLED', true),

        /*
         * Where third-party CLI tools are installed. Not web-accessible.
         */
        'path' => storage_path('app/ai/tools'),
    ],

    'scripts' => [
        'enabled' => env('AI_TOOLBOX_SCRIPTS_ENABLED', true),

        /*
         * When the RunSkillScript tool requires human approval before a script
         * runs: "untrusted" (only untrusted skills), "always", "never".
         */
        'approval' => env('AI_TOOLBOX_SCRIPTS_APPROVAL', 'untrusted'),

        'timeout' => env('AI_TOOLBOX_SCRIPTS_TIMEOUT', 30),

        /*
         * Maximum bytes of combined output returned to the model.
         */
        'max_output' => 65536,

        /*
         * Allowed script extensions mapped to their interpreter binary.
         */
        'runtimes' => [
            'py' => env('AI_TOOLBOX_PYTHON_BINARY', 'python3'),
            'js' => env('AI_TOOLBOX_NODE_BINARY', 'node'),
            'mjs' => env('AI_TOOLBOX_NODE_BINARY', 'node'),
        ],

        /*
         * The only environment variables passed to the child process.
         */
        'environment' => ['PATH', 'HOME', 'LANG'],
    ],

];
