<?php

namespace App\Providers;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Http\Message\ResponseInterface;

class MauticOAuthProvider extends AbstractProvider
{

    public function register()
    {
        $this->app->singleton(AbstractProvider::class, function ($app) {
            $config = $app['config']['services.mautic'];

            return new MauticOAuthProvider([
                'clientId' => $config['client_id'],
                'clientSecret' => $config['client_secret'],
                'redirectUri' => $config['redirect_uri'],
                'urlAuthorize' => $config['base_url'] . '/oauth/v2/authorize',
                'urlAccessToken' => $config['base_url'] . '/oauth/v2/token',
                'urlResourceOwnerDetails' => $config['base_url'] . '/user',
            ]);
        });
    }
    /**
     * URL de base pour les requêtes d'autorisation
     *
     * @return string
     */
    public function getBaseAuthorizationUrl()
    {
        return 'http://localhost:8003/oauth/v2/authorize';
    }

    /**
     * URL de base pour échanger le code d'autorisation contre un jeton d'accès
     *
     * @return string
     */
    public function getBaseAccessTokenUrl(array $params)
    {
        return 'http://localhost:8003/oauth/v2/token';
    }

    /**
     * Retourne l'URL pour récupérer les détails du propriétaire de la ressource
     *
     * @param AccessToken $token
     * @return string
     */
    public function getResourceOwnerDetailsUrl(AccessToken $token)
    {
        // Mautic ne fournit pas cette fonctionnalité, vous pouvez retourner une URL factice
        return 'https://api.mautic.com/user';
    }

    // Implémentez les autres méthodes nécessaires selon vos besoins
}
