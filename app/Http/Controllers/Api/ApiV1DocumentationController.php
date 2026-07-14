<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ApiV1DocumentationController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json($this->spec());
    }

    /**
     * @return array<string, mixed>
     */
    private function spec(): array
    {
        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'Multi-tenant Core Platform API',
                'version' => '1.0.0',
                'description' => 'Verzionirani REST API za platform identity, billing, GDPR i SaaS integracije.',
            ],
            'servers' => [
                ['url' => '/api/v1'],
            ],
            'paths' => [
                '/auth/login' => [
                    'post' => [
                        'summary' => 'Platform login',
                        'tags' => ['Auth'],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['email', 'password'],
                                        'properties' => [
                                            'email' => ['type' => 'string', 'format' => 'email'],
                                            'password' => ['type' => 'string'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Bearer token issued'],
                            '422' => ['description' => 'Validation error'],
                        ],
                    ],
                ],
                '/auth/me' => [
                    'get' => [
                        'summary' => 'Current platform user',
                        'tags' => ['Auth'],
                        'security' => [['bearerAuth' => []]],
                        'responses' => [
                            '200' => ['description' => 'Authenticated user profile'],
                            '401' => ['description' => 'Unauthorized'],
                        ],
                    ],
                ],
                '/auth/data-export' => [
                    'get' => [
                        'summary' => 'GDPR data export',
                        'tags' => ['GDPR'],
                        'security' => [['bearerAuth' => []]],
                        'parameters' => [
                            ['name' => 'format', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['json', 'csv']]],
                            ['name' => 'inline', 'in' => 'query', 'schema' => ['type' => 'boolean']],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Export payload or file'],
                            '429' => ['description' => 'Rate limited'],
                        ],
                    ],
                ],
                '/auth/account-deletion' => [
                    'get' => [
                        'summary' => 'GDPR account deletion status',
                        'tags' => ['GDPR'],
                        'security' => [['bearerAuth' => []]],
                        'responses' => [
                            '200' => ['description' => 'Deletion workflow status'],
                        ],
                    ],
                    'post' => [
                        'summary' => 'Request account deletion (grace period)',
                        'tags' => ['GDPR'],
                        'security' => [['bearerAuth' => []]],
                        'responses' => [
                            '202' => ['description' => 'Deletion scheduled'],
                            '422' => ['description' => 'Validation error'],
                        ],
                    ],
                    'delete' => [
                        'summary' => 'Cancel pending account deletion',
                        'tags' => ['GDPR'],
                        'security' => [['bearerAuth' => []]],
                        'responses' => [
                            '200' => ['description' => 'Deletion cancelled'],
                        ],
                    ],
                ],
                '/platform/workspaces' => [
                    'get' => [
                        'summary' => 'List user workspaces',
                        'tags' => ['Workspaces'],
                        'security' => [['bearerAuth' => []]],
                        'responses' => [
                            '200' => ['description' => 'Workspace list'],
                        ],
                    ],
                ],
                '/platform/billing/checkout' => [
                    'post' => [
                        'summary' => 'Start Stripe checkout or plan change',
                        'tags' => ['Billing'],
                        'security' => [['saasWebhookSecret' => []]],
                        'responses' => [
                            '200' => ['description' => 'Checkout session or plan update'],
                        ],
                    ],
                ],
                '/platform/events/dispatch' => [
                    'post' => [
                        'summary' => 'Dispatch customer webhook event',
                        'tags' => ['Webhooks'],
                        'security' => [['saasWebhookSecret' => []]],
                        'responses' => [
                            '200' => ['description' => 'Event queued for delivery'],
                        ],
                    ],
                ],
            ],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                    ],
                    'saasWebhookSecret' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'description' => 'SAAS_WEBHOOK_SECRET shared with modules',
                    ],
                ],
            ],
        ];
    }
}
