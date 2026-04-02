<?php

declare(strict_types=1);

namespace App\Tests\Contract;

use App\ExchangeRate\Domain\Service\PolishWorkingDayResolver;
use App\ExchangeRate\Infrastructure\NBP\NBPApiClient;
use App\Shared\Domain\ValueObject\CurrencyCode;
use PhpPact\Consumer\InteractionBuilder;
use PhpPact\Consumer\Matcher\Matcher;
use PhpPact\Consumer\Model\ConsumerRequest;
use PhpPact\Consumer\Model\ProviderResponse;
use PhpPact\Standalone\MockService\MockServerConfig;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Consumer contract test for NBP API (api.nbp.pl).
 *
 * Verifies that TaxPilot's expectations of the NBP API response structure
 * are formally documented as a Pact contract.
 *
 * The Pact mock server stands in for api.nbp.pl. A URL-rewriting HttpClient
 * redirects NBPApiClient's requests from the real host to the mock server.
 *
 * Pact files are written to tests/pacts/.
 */
final class NBPApiConsumerTest extends TestCase
{
    private const string CONSUMER = 'TaxPilot';

    private const string PROVIDER = 'NBP_API';

    private const string PACT_DIR = __DIR__ . '/../pacts';

    private Matcher $matcher;

    private MockServerConfig $config;

    protected function setUp(): void
    {
        $this->matcher = new Matcher();

        $this->config = new MockServerConfig();
        $this->config
            ->setConsumer(self::CONSUMER)
            ->setProvider(self::PROVIDER)
            ->setPactDir(self::PACT_DIR)
            ->setPactSpecificationVersion('3.0.0');

        if ($logLevel = getenv('PACT_LOGLEVEL')) {
            $this->config->setLogLevel($logLevel);
        }
    }

    public function testGetSingleDayExchangeRate(): void
    {
        $request = new ConsumerRequest();
        $request
            ->setMethod('GET')
            ->setPath('/api/exchangerates/rates/a/usd/2025-03-18/')
            ->addQueryParameter('format', 'json');

        $response = new ProviderResponse();
        $response
            ->setStatus(200)
            ->addHeader('Content-Type', 'application/json')
            ->setBody([
                'table' => $this->matcher->like('A'),
                'currency' => $this->matcher->like('dolar amerykanski'),
                'code' => $this->matcher->like('USD'),
                'rates' => $this->matcher->eachLike([
                    'no' => $this->matcher->regex('055/A/NBP/2025', '\d{3}/A/NBP/\d{4}'),
                    'effectiveDate' => $this->matcher->dateISO8601('2025-03-18'),
                    'mid' => $this->matcher->decimal(4.0512),
                ]),
            ]);

        $builder = new InteractionBuilder($this->config);
        $builder
            ->given('USD exchange rate exists for 2025-03-18')
            ->uponReceiving('a request for USD exchange rate on 2025-03-18')
            ->with($request)
            ->willRespondWith($response);

        // Act: redirect NBPApiClient requests to the Pact mock server
        $httpClient = $this->createRewritingHttpClient();
        $workingDayResolver = new PolishWorkingDayResolver();

        $client = new NBPApiClient($httpClient, $workingDayResolver);

        // Transaction on 2025-03-19 (Wed) -> rate from 2025-03-18 (Tue)
        $rate = $client->getRateForDate(CurrencyCode::USD, new \DateTimeImmutable('2025-03-19'));

        self::assertTrue($rate->rate()->isEqualTo('4.0512'));
        self::assertTrue($rate->currency()->equals(CurrencyCode::USD));
        self::assertSame('2025-03-18', $rate->effectiveDate()->format('Y-m-d'));

        $verified = $builder->verify();
        self::assertTrue($verified, 'Pact verification failed: mock server did not receive expected request');
    }

    public function testGetExchangeRateReturns404WhenNotAvailable(): void
    {
        // NBPApiClient requests multiple dates when falling back.
        // We register a 404 for a specific date.
        $request = new ConsumerRequest();
        $request
            ->setMethod('GET')
            ->setPath('/api/exchangerates/rates/a/usd/2025-12-25/')
            ->addQueryParameter('format', 'json');

        $response = new ProviderResponse();
        $response->setStatus(404);

        $builder = new InteractionBuilder($this->config);
        $builder
            ->given('no USD exchange rate exists for 2025-12-25 (Christmas)')
            ->uponReceiving('a request for USD exchange rate on a non-publishing day')
            ->with($request)
            ->willRespondWith($response);

        // Directly hit the mock server to verify 404 contract
        $mockBaseUri = (string) $this->config->getBaseUri();
        $httpClient = HttpClient::create();
        $directResponse = $httpClient->request(
            'GET',
            $mockBaseUri . '/api/exchangerates/rates/a/usd/2025-12-25/?format=json',
            [
                'timeout' => 5,
            ],
        );

        self::assertSame(404, $directResponse->getStatusCode());

        $verified = $builder->verify();
        self::assertTrue($verified, 'Pact verification failed');
    }

    public function testGetExchangeRatesForDateRange(): void
    {
        $request = new ConsumerRequest();
        $request
            ->setMethod('GET')
            ->setPath('/api/exchangerates/rates/a/usd/2025-03-12/2025-03-14/')
            ->addQueryParameter('format', 'json');

        $response = new ProviderResponse();
        $response
            ->setStatus(200)
            ->addHeader('Content-Type', 'application/json')
            ->setBody([
                'table' => $this->matcher->like('A'),
                'currency' => $this->matcher->like('dolar amerykanski'),
                'code' => $this->matcher->like('USD'),
                'rates' => $this->matcher->eachLike([
                    'no' => $this->matcher->regex('050/A/NBP/2025', '\d{3}/A/NBP/\d{4}'),
                    'effectiveDate' => $this->matcher->dateISO8601('2025-03-12'),
                    'mid' => $this->matcher->decimal(4.0412),
                ]),
            ]);

        $builder = new InteractionBuilder($this->config);
        $builder
            ->given('USD exchange rates exist for 2025-03-12 to 2025-03-14')
            ->uponReceiving('a request for USD exchange rates for a date range')
            ->with($request)
            ->willRespondWith($response);

        $httpClient = $this->createRewritingHttpClient();
        $workingDayResolver = new PolishWorkingDayResolver();

        $client = new NBPApiClient($httpClient, $workingDayResolver);
        $rates = $client->getRatesForDateRange(
            CurrencyCode::USD,
            new \DateTimeImmutable('2025-03-12'),
            new \DateTimeImmutable('2025-03-14'),
        );

        self::assertNotEmpty($rates);

        $verified = $builder->verify();
        self::assertTrue($verified, 'Pact verification failed');
    }

    /**
     * Creates an HttpClient that rewrites NBP API URLs to point at the Pact mock server.
     *
     * NBPApiClient builds absolute URLs like https://api.nbp.pl/api/exchangerates/...
     * This wrapper replaces the host portion with the mock server's address.
     */
    private function createRewritingHttpClient(): HttpClientInterface
    {
        $mockBaseUri = (string) $this->config->getBaseUri();

        return new class($mockBaseUri) implements HttpClientInterface {
            public function __construct(
                private readonly string $mockBaseUri,
                private readonly HttpClientInterface $inner = new \Symfony\Component\HttpClient\CurlHttpClient(),
            ) {
            }

            /**
             * @param array<string, mixed> $options
             */
            public function request(string $method, string $url, array $options = []): ResponseInterface
            {
                // Rewrite: https://api.nbp.pl/api/... -> http://localhost:PORT/api/...
                $rewritten = preg_replace(
                    '#^https?://api\.nbp\.pl#',
                    rtrim($this->mockBaseUri, '/'),
                    $url,
                );

                return $this->inner->request($method, $rewritten ?? $url, $options);
            }

            public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): \Symfony\Contracts\HttpClient\ResponseStreamInterface
            {
                return $this->inner->stream($responses, $timeout);
            }

            /**
             * @param array<string, mixed> $options
             */
            public function withOptions(array $options): static
            {
                return $this;
            }
        };
    }
}
