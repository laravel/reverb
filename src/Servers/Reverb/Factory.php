<?php

namespace Laravel\Reverb\Servers\Reverb;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelConnectionManager;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelManager;
use Laravel\Reverb\Protocols\Pusher\Http\Controllers\ChannelController;
use Laravel\Reverb\Protocols\Pusher\Http\Controllers\ChannelsController;
use Laravel\Reverb\Protocols\Pusher\Http\Controllers\ChannelUsersController;
use Laravel\Reverb\Protocols\Pusher\Http\Controllers\ConnectionsController;
use Laravel\Reverb\Protocols\Pusher\Http\Controllers\EventsBatchController;
use Laravel\Reverb\Protocols\Pusher\Http\Controllers\EventsController;
use Laravel\Reverb\Protocols\Pusher\Http\Controllers\PusherController;
use Laravel\Reverb\Protocols\Pusher\Http\Controllers\UsersTerminateController;
use Laravel\Reverb\Protocols\Pusher\Managers\ArrayChannelConnectionManager;
use Laravel\Reverb\Protocols\Pusher\Managers\ArrayChannelManager;
use Laravel\Reverb\Protocols\Pusher\PusherPubSubIncomingMessageHandler;
use Laravel\Reverb\Protocols\Pusher\Server as PusherServer;
use Laravel\Reverb\Servers\Reverb\Contracts\PubSubIncomingMessageHandler;
use Laravel\Reverb\Servers\Reverb\Http\Route;
use Laravel\Reverb\Servers\Reverb\Http\Router;
use Laravel\Reverb\Servers\Reverb\Http\Server as HttpServer;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Socket\SocketServer;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Throwable;

class Factory
{
    /**
     * Create a new WebSocket server instance.
     */
    public static function make(string $host = '0.0.0.0', string $port = '8080', bool $secure = false, array $tlsOptions = [], string $protocol = 'pusher', ?LoopInterface $loop = null): HttpServer
    {
        $loop = $loop ?: Loop::get();

        $router = match ($protocol) {
            'pusher' => static::makePusherServer(),
            default => throw new InvalidArgumentException("Unsupported protocol [{$protocol}]."),
        };

        return new HttpServer(
            new SocketServer("{$host}:{$port}", [], $loop),
            $router,
            $loop
        );
    }

    /**
     * Create a new WebSocket server for the Pusher protocol.
     */
    public static function makePusherServer(): Router
    {
        app()->singleton(
            ChannelManager::class,
            fn () => new ArrayChannelManager
        );

        app()->bind(
            ChannelConnectionManager::class,
            fn () => new ArrayChannelConnectionManager
        );

        app()->singleton(
            PubSubIncomingMessageHandler::class,
            fn () => new PusherPubSubIncomingMessageHandler,
        );

        return new Router(new UrlMatcher(static::pusherRoutes(), new RequestContext));
    }

    /**
     * Generate the routes required to handle Pusher requests.
     */
    protected static function pusherRoutes(): RouteCollection
    {
        $routes = new RouteCollection;

        $routes->add('sockets', Route::get('/app/{appKey}', new PusherController(app(PusherServer::class), app(ApplicationProvider::class))));
        $routes->add('events', Route::post('/apps/{appId}/events', new EventsController));
        $routes->add('events_batch', Route::post('/apps/{appId}/batch_events', new EventsBatchController));
        $routes->add('connections', Route::get('/apps/{appId}/connections', new ConnectionsController));
        $routes->add('channels', Route::get('/apps/{appId}/channels', new ChannelsController));
        $routes->add('channel', Route::get('/apps/{appId}/channels/{channel}', new ChannelController));
        $routes->add('channel_users', Route::get('/apps/{appId}/channels/{channel}/users', new ChannelUsersController));
        $routes->add('users_terminate', Route::post('/apps/{appId}/users/{userId}/terminate_connections', new UsersTerminateController));

        return $routes;
    }

    /**
     * Find or create a TLS certificate for the given host and return the path.
     */
    protected static function ensureCertificateExists(string $host)
    {
        $path = storage_path('app/reverb');

        File::ensureDirectoryExists($path);

        $certificate = $path."/{$host}.pem";

        if (File::missing($certificate) || static::certificateIsInvalid($certificate)) {
            File::replace($path, static::createCertificate($host));
        }

        return $path;
    }

    /**
     * Create a new TLS certificate for the given host.
     */
    protected static function createCertificate(string $host): string
    {
        $privateKey = openssl_pkey_new([
            'private_key_bits' => 4096,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $signingRequest = openssl_csr_new([
            'commonName' => $host,
            'countryName' => 'US',
            'organizationName' => 'Laravel Reverb CA Self Signed Organization',
            'organizationalUnitName' => 'Developers',
            'emailAddress' => 'certificate@laravel.reverb',
        ], $privateKey);
        $certificate = openssl_csr_sign($signingRequest, null, $privateKey, 365);

        openssl_x509_export($certificate, $exportedCertificate);
        openssl_pkey_export($privateKey, $exportedPrivateKey);

        return $exportedCertificate.$exportedPrivateKey;
    }

    /**
     * Determine if the certificate at the given path is invalid.
     */
    public static function certificateIsInvalid(string $path): bool
    {
        try {
            $certificate = openssl_x509_parse(file_get_contents($path));
        } catch (Throwable) {
            return true;
        }

        return time() > $certificate['validTo_time_t'];
    }
}
