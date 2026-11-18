<?php

namespace Tests;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Exception\ElementClickInterceptedException;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\Exception\StaleElementReferenceException;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Laravel\Dusk\TestCase as BaseTestCase;
use PHPUnit\Framework\Attributes\BeforeClass;

abstract class DuskTestCase extends BaseTestCase
{
    /**
     * Prepare for Dusk test execution.
     */
    #[BeforeClass]
    public static function prepare(): void
    {
        if (! static::runningInSail()) {
            static::startChromeDriver(['--port=9515']);
        }
    }

    /**
     * Create a new Browser instance with resilient navigation & DOM assertions.
     */
    protected function newBrowser($driver): Browser
    {
        return new class($driver) extends Browser
        {
            public function press($button)
            {
                $element = $this->resolver->resolveForButtonPress($button);
                $isSubmit = strtolower((string) $element->getAttribute('type')) === 'submit';

                try {
                    $element->click();
                } catch (ElementClickInterceptedException) {
                    $this->driver->executeScript(
                        'arguments[0].scrollIntoView({block: "center", inline: "nearest"});',
                        [$element]
                    );
                    $this->pause(100);
                    $element->click();
                }

                if ($isSubmit) {
                    $this->pause(350);
                    $this->waitUsing(5, 100, function () {
                        return $this->driver->executeScript('return document.readyState') === 'complete';
                    });
                }

                return $this;
            }

            public function assertSeeIn($selector, $text, $ignoreCase = false)
            {
                if ($text !== '') {
                    $deadline = microtime(true) + 3.0;
                    while (microtime(true) < $deadline) {
                        try {
                            $element = $this->resolver->findOrFail($selector);
                            if (Str::contains($element->getText(), $text, $ignoreCase)) {
                                break;
                            }
                        } catch (StaleElementReferenceException|NoSuchElementException) {
                            // Wait for in-flight redirect or DOM replacement to settle
                        }
                        usleep(150000);
                    }
                }

                return parent::assertSeeIn($selector, $text, $ignoreCase);
            }

            public function assertDontSeeIn($selector, $text, $ignoreCase = false)
            {
                $deadline = microtime(true) + 2.0;
                while (microtime(true) < $deadline) {
                    try {
                        $element = $this->resolver->findOrFail($selector);
                        $element->getText();
                        break;
                    } catch (StaleElementReferenceException) {
                        usleep(150000);
                    }
                }

                return parent::assertDontSeeIn($selector, $text, $ignoreCase);
            }
        };
    }

    /**
     * Create the RemoteWebDriver instance.
     */
    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions)->addArguments(collect([
            $this->shouldStartMaximized() ? '--start-maximized' : '--window-size=1920,1080',
            '--disable-search-engine-choice-screen',
            '--disable-smooth-scrolling',
            '--disable-extensions',
            '--host-resolver-rules=MAP *.kaspersky-labs.com 127.0.0.1',
        ])->unless($this->hasHeadlessDisabled(), function (Collection $items) {
            return $items->merge([
                '--disable-gpu',
                '--headless=new',
            ]);
        })->all());

        return RemoteWebDriver::create(
            $_ENV['DUSK_DRIVER_URL'] ?? env('DUSK_DRIVER_URL') ?? 'http://localhost:9515',
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY, $options
            )
        );
    }
}
