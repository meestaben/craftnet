<?php

return [
    '*' => [
        'craftIdUrl' => 'https://id.craftcms.com',
        'stripePublicKey' => getenv('STRIPE_PUBLIC_KEY'),
        'stripeApiKey' => getenv('STRIPE_API_KEY'),
        'stripeClientId' => getenv('STRIPE_CLIENT_ID'),
        'oauthServer' => [
            'accessTokenExpiry' => 'PT1H',
            'refreshTokenExpiry' => 'P1M',
            'authCodeExpiry' => 'P1M',
            'clientApprovalTemplate' => 'oauth/clientApproval',
            'enabledGrants' => [
                'ClientCredentialsGrant',
                'PasswordGrant',
                'RefreshTokenGrant',
                'ImplicitGrant',
                'AuthCodeGrant',
            ],
            'grants' => [
                'ClientCredentialsGrant' => 'Client Credentials Grant',
                'PasswordGrant' => 'Password Grant',
                'AuthCodeGrant' => 'Authorization Code Grant',
                'ImplicitGrant' => 'Implicit Grant',
                'RefreshTokenGrant' => 'Refresh Token Grant',
            ],
            'privateKey' => __DIR__ . '/keys/oauth-server',
            'publicKey' => __DIR__ . '/keys/oauth-server.pub',
            'encryptionKey' => getenv('OAUTH_ENC_KEY'),
            'scopes' => [
                'purchasePlugins' => "Purchase plugins",
                'existingPlugins' => "List existing plugins",
                'transferPluginLicense' => "Transfer plugin license",
                'deassociatePluginLicense' => "Deassociate plugin license",
            ]
        ],
        'enablePluginStoreCache' => false,
    ],
    'prod' => [
        'enablePluginStoreCache' => true,
    ],
    'stage' => [
        'craftIdUrl' => 'https://staging-1750ml.id.craftcms.com/',
    ],
    'dev' => [
        'craftIdUrl' => 'https://id.craftcms.test',
    ],
    'next' => [
        'craftIdUrl' => 'https://id.craftcms.next',
    ]
];
