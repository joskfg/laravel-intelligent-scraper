<?php

namespace Joskfg\LaravelIntelligentScraper\Scraper\Application;

use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class XpathBuilderTest extends TestCase
{
    private ?\DOMElement $domElement = null;

    private \Joskfg\LaravelIntelligentScraper\Scraper\Application\XpathBuilder $xpathBuilder;

    public function setUp(): void
    {
        parent::setUp();

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML($this->getHtml());

        Log::spy();

        $this->domElement   = $dom->documentElement;
        $this->xpathBuilder = new XpathBuilder('/^random-.*$/');
    }

    private function getHtml(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <meta name="id" content="12345">
</head>
<body>
    <h2>The target Attribute</h2>

    <div id="page-title">
        <div>
            <h1>My Title</h1>
            <h2>12345</h2>
        </div>
        <div>
            <p>Some content</p>
        </div>
    </div>
    
    <img src="http://test.com/image.jpg">
    
    <div id="page-description">
        <h2>Description</h2>
        <p>
            If you set the target attribute to "_blank", 
            the link will open in a new browser window or tab.
        </p>
        <div id="random-id">
            Image List
            <div>
                <a href="">
                    <img src="http://test.com/image1.jpg">
                </a>
                <a href="">
                    <img src="http://test.com/image2.jpg">
                </a>
            </div>
            <div>
                <a href="">
                    <img src="http://test.com/image3.jpg">
                </a>
                <a href="">
                    <img src="http://test.com/image4.jpg">
                </a>
            </div>
        </div>
        <div></div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * @test
     */
    public function whenTextIsNotFoundItShouldThrowAnException(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("'unknown value' not found");

        $this->xpathBuilder->find($this->domElement, 'unknown value');
    }

    /**
     * @test
     */
    public function whenRegexpIsNotFoundItShouldThrowAnException(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("'/unknown value/' not found");

        $this->xpathBuilder->find($this->domElement, regexp('/unknown value/'));
    }

    /**
     * @test
     */
    public function whenTextIsFoundWithinAParentWithIdItShouldReturnTheXPathBasedInTheParentId(): void
    {
        self::assertEquals(
            '//*[@id="page-description"]/h2',
            $this->xpathBuilder->find($this->domElement, 'Description')
        );
    }

    /**
     * @test
     */
    public function whenTextIsFoundWithSiblingsItShouldSetTheElementPosition(): void
    {
        self::assertEquals(
            '//*[@id="page-title"]/div[2]/p',
            $this->xpathBuilder->find($this->domElement, 'Some content')
        );
    }

    /**
     * @test
     */
    public function whenTextIsFoundButThereAreNoParentsWithIdItShouldReturnXpathUntilRoot(): void
    {
        self::assertEquals(
            '/html/body/h2',
            $this->xpathBuilder->find($this->domElement, 'The target Attribute')
        );
    }

    /**
     * @test
     */
    public function whenMultiLineTextIsFoundItShouldReturnTheXpathToTheNode(): void
    {
        $text = <<<'TEXT'

            If you set the target attribute to "_blank", 
            the link will open in a new browser window or tab.
        
TEXT;

        self::assertEquals(
            '//*[@id="page-description"]/p',
            $this->xpathBuilder->find($this->domElement, $text)
        );
    }

    /**
     * @test
     */
    public function whenRegexIsFoundItShouldReturnTheXpathToTheNode(): void
    {
        $text = regexp(
            '/^\s*If you set the target attribute to "_blank",\s*the link will open in a new browser window or tab.\s*$/'
        );

        self::assertEquals(
            '//*[@id="page-description"]/p',
            $this->xpathBuilder->find($this->domElement, $text)
        );
    }

    /**
     * @test
     */
    public function whenGetInformationThatIsInAAttributeItShouldGenerateAValidXpath(): void
    {
        self::assertEquals(
            '/html/body/img/@src',
            $this->xpathBuilder->find($this->domElement, 'http://test.com/image.jpg')
        );
    }

    /**
     * @test
     */
    public function whenTryToFindACommonPatternBetweenDifferentValuesItShouldReturnAnXpathWithCommonParts(): void
    {
        $values = [
            'http://test.com/image1.jpg',
            'http://test.com/image2.jpg',
            'http://test.com/image3.jpg',
            'http://test.com/image4.jpg',
        ];

        self::assertEquals(
            '//*[@id="page-description"]/div[1]/*/*/img/@src',
            $this->xpathBuilder->find($this->domElement, $values)
        );
    }

    /**
     * @test
     */
    public function whenTryToFindACommonPatternBetweenDifferentRegexpItShouldReturnAnXpathWithCommonParts(): void
    {
        $values = [
            regexp('@^http://test.com/image1.jpg$@'),
            regexp('@^http://test.com/image2.jpg$@'),
            regexp('@^http://test.com/image3.jpg$@'),
            regexp('@^http://test.com/image4.jpg$@'),
        ];

        self::assertEquals(
            '//*[@id="page-description"]/div[1]/*/*/img/@src',
            $this->xpathBuilder->find($this->domElement, $values)
        );
    }

    /**
     * @test
     */
    public function whenTheInformationIsInAMetaItShouldTargetTheSpecificMeta(): void
    {
        $values = ['12345'];

        self::assertEquals(
            '//meta[@name="id"]/@content',
            $this->xpathBuilder->find($this->domElement, $values)
        );
    }
}
