<?php

namespace Joskfg\LaravelIntelligentScraper\Scraper\Application;

use Goutte\Client;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Log;
use Joskfg\LaravelIntelligentScraper\Scraper\Exceptions\MissingXpathValueException;
use Joskfg\LaravelIntelligentScraper\Scraper\Models\Configuration;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\Exception\TransportException;
use Tests\TestCase;
use Tests\Unit\Fakes\FakeHttpException;

class XpathFinderTest extends TestCase
{
    use DatabaseMigrations;

    public function setUp(): void
    {
        parent::setUp();

        Log::spy();
    }

    /**
     * @test
     */
    public function whenExtractUsingAnInvalidUrlStatusItShouldThrowAnException(): void
    {
        $config = [
            Configuration::create([
                'name'   => ':field:',
                'type'   => ':type:',
                'xpaths' => [':xpath:'],
            ]),
        ];

        $variantGenerator = \Mockery::mock(VariantGenerator::class);

        $requestException = \Mockery::mock(FakeHttpException::class);
        $requestException->shouldReceive('getResponse->getStatusCode')
            ->once()
            ->andReturn(404);

        $client = \Mockery::mock(Client::class);
        $client->shouldReceive('request')
            ->once()
            ->with(
                'GET',
                ':url:'
            )
            ->andThrows($requestException);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Response error from \':url:\' with \'404\' http code');

        $xpathFinder = new XpathFinder($client, $variantGenerator);
        $xpathFinder->extract(':url:', collect($config));
    }

    /**
     * @test
     */
    public function whenExtractUsingAnUnavailableUrlItShouldThrowAnException(): void
    {
        $config = [
            Configuration::create([
                'name'   => ':field:',
                'type'   => ':type:',
                'xpaths' => [':xpath:'],
            ]),
        ];

        $variantGenerator = \Mockery::mock(VariantGenerator::class);

        $connectException = \Mockery::mock(TransportException::class);
        $client           = \Mockery::mock(Client::class);
        $client->shouldReceive('request')
            ->once()
            ->with(
                'GET',
                ':url:'
            )
            ->andThrows($connectException);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Unavailable url \':url:\'');

        $xpathFinder = new XpathFinder($client, $variantGenerator);
        $xpathFinder->extract(':url:', collect($config));
    }

    /**
     * @test
     */
    public function whenXpathIsMissingAValueItShouldThrowAnException(): void
    {
        $config = [
            Configuration::create([
                'name'   => ':field:',
                'type'   => ':type:',
                'xpaths' => [
                    ':xpath-1:',
                    ':xpath-2:',
                ],
            ]),
        ];

        $internalXpathFinder = \Mockery::mock(\Symfony\Component\DomCrawler\Crawler::class);

        $variantGenerator = \Mockery::mock(VariantGenerator::class);
        $client           = \Mockery::mock(Client::class);
        $client->shouldReceive('request')
            ->once()
            ->with(
                'GET',
                ':url:'
            )
            ->andReturn($internalXpathFinder);

        $internalXpathFinder->shouldReceive('evaluate')
            ->once()
            ->with(':xpath-1:')
            ->andReturnSelf();
        $internalXpathFinder->shouldReceive('evaluate')
            ->once()
            ->with(':xpath-2:')
            ->andReturnSelf();
        $internalXpathFinder->shouldReceive('count')
            ->andReturn(0);

        $this->expectException(MissingXpathValueException::class);
        $this->expectExceptionMessage('Xpath \':xpath-1:\', \':xpath-2:\' for field \':field:\' not found in \':url:\'.');

        $xpathFinder = new XpathFinder($client, $variantGenerator);
        $xpathFinder->extract(':url:', collect($config));
    }

    /**
     * @test
     */
    public function whenXpathsAreFoundItShouldReturnTheFoundValues(): void
    {
        $config = [
            Configuration::create([
                'name'       => ':field-1:',
                'type'       => ':type:',
                'chain_type' => ':chain-type:',
                'xpaths'     => [
                    ':xpath-1:',
                    ':xpath-2:',
                ],
            ]),
            Configuration::create([
                'name'   => ':field-2:',
                'type'   => ':type:',
                'xpaths' => [
                    ':xpath-3:',
                    ':xpath-4:',
                ],
            ]),
        ];

        $internalXpathFinder = \Mockery::mock(Crawler::class);
        $titleXpathFinder    = \Mockery::mock(Crawler::class);
        $authorXpathFinder   = \Mockery::mock(Crawler::class);

        $variantGenerator = \Mockery::mock(VariantGenerator::class);
        $variantGenerator->shouldReceive('addConfig')
            ->twice();
        $variantGenerator->shouldReceive('getId')
            ->andReturn(':variant:');

        $client = \Mockery::mock(Client::class);
        $client->shouldReceive('request')
            ->once()
            ->with(
                'GET',
                ':url:'
            )
            ->andReturn($internalXpathFinder);

        $internalXpathFinder->shouldReceive('evaluate')
            ->once()
            ->with(':xpath-1:')
            ->andReturnSelf();
        $internalXpathFinder->shouldReceive('evaluate')
            ->once()
            ->with(':xpath-2:')
            ->andReturn($titleXpathFinder);
        $internalXpathFinder->shouldReceive('evaluate')
            ->once()
            ->with(':xpath-3:')
            ->andReturn($authorXpathFinder);
        $internalXpathFinder->shouldReceive('evaluate')
            ->never()
            ->with(':xpath-4:');
        $internalXpathFinder->shouldReceive('count')
            ->andReturn(0);
        $titleXpathFinder->shouldReceive('count')
            ->andReturn(1);
        $authorXpathFinder->shouldReceive('count')
            ->andReturn(1);
        $authorXpathFinder->shouldReceive('each')
            ->andReturn([':value-1:']);
        $titleXpathFinder->shouldReceive('each')
            ->andReturn([':value-2:']);

        $xpathFinder   = new XpathFinder($client, $variantGenerator);
        $extractedData = $xpathFinder->extract(':url:', collect($config));

        self::assertSame(
            ':variant:',
            $extractedData->getVariant()
        );

        $field1 = $extractedData->getField(':field-1:');
        self::assertSame([':value-2:'], $field1->getValue());
        self::assertSame(':chain-type:', $field1->getChainType());
        self::assertTrue($field1->isFound());

        $field2 = $extractedData->getField(':field-2:');
        self::assertSame([':value-1:'], $field2->getValue());
        self::assertNull($field2->getChainType());
        self::assertTrue($field2->isFound());
    }
}
