<?php

namespace BlueFission\Tests;

use BlueFission\Wise\Nav\SynthetiqBootstrap;
use BlueFission\Wise\Nav\SynthetiqProxy;
use BlueFission\Wise\Sys\DirectoryManager;
use PHPUnit\Framework\TestCase;

final class SynthetiqBootstrapTest extends TestCase
{
    /**
     * @group integration
     */
    public function testBootstrapCreatesNavigatorAndResponds(): void
    {
        $vendorPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bluefission' . DIRECTORY_SEPARATOR . 'synthetiq' . DIRECTORY_SEPARATOR . 'sample_configs';
        if (!DirectoryManager::pathExists($vendorPath)) {
            $this->markTestSkipped('Synthetiq sample configs not available.');
        }

        $modelPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wise-synthetiq-models';

        $navigator = SynthetiqBootstrap::fromVendorSampleConfigs($vendorPath, $modelPath);

        $this->assertInstanceOf(SynthetiqProxy::class, $navigator);
        $response = $navigator->process('hello');

        $this->assertIsString($response);
        $this->assertNotSame('', $response);
    }

    public function testMinimalConfigProducesResponse(): void
    {
        $vendorPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bluefission' . DIRECTORY_SEPARATOR . 'synthetiq' . DIRECTORY_SEPARATOR . 'sample_configs';
        if (!DirectoryManager::pathExists($vendorPath)) {
            $this->markTestSkipped('Synthetiq sample configs not available.');
        }

        $config = [
            'dialogue' => [
                'wise.test.intent' => ['wise.test.intent', ['wise test response'], ['wise-intent']],
            ],
            'intent_boosts' => [],
            'grammar' => require $vendorPath . DIRECTORY_SEPARATOR . 'grammar.php',
            'tokens' => require $vendorPath . DIRECTORY_SEPARATOR . 'tokens.php',
            'documenter' => require $vendorPath . DIRECTORY_SEPARATOR . 'documenter.php',
            'model_path' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wise-synthetiq-models-min',
            'intent_keywords' => [
                'wise.test.intent' => [
                    'keywords' => ['wise-intent'],
                    'priority' => 20,
                ],
            ],
            'routes' => [
                ['wise test response', 'wise.test.intent'],
            ],
        ];

        $navigator = SynthetiqBootstrap::fromConfig($config);
        $response = $navigator->process('wise-intent');

        $this->assertIsString($response);
        $this->assertNotSame('', $response);
    }

    /**
     * @group integration
     */
    public function testBootstrapAppliesCustomRoutesAndKeywords(): void
    {
        $vendorPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bluefission' . DIRECTORY_SEPARATOR . 'synthetiq' . DIRECTORY_SEPARATOR . 'sample_configs';
        if (!DirectoryManager::pathExists($vendorPath)) {
            $this->markTestSkipped('Synthetiq sample configs not available.');
        }

        $config = [
            'dialogue' => require $vendorPath . DIRECTORY_SEPARATOR . 'dialogue.php',
            'intent_boosts' => require $vendorPath . DIRECTORY_SEPARATOR . 'intent_boosts.php',
            'grammar' => require $vendorPath . DIRECTORY_SEPARATOR . 'grammar.php',
            'tokens' => require $vendorPath . DIRECTORY_SEPARATOR . 'tokens.php',
            'documenter' => require $vendorPath . DIRECTORY_SEPARATOR . 'documenter.php',
            'model_path' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wise-synthetiq-models-custom',
            'intent_keywords' => [
                'wise.test.intent' => [
                    'keywords' => ['wise-intent'],
                    'priority' => 20,
                ],
            ],
            'routes' => [
                ['wise test response', 'wise.test.intent'],
            ],
        ];

        $navigator = SynthetiqBootstrap::fromConfig($config);
        $response = $navigator->process('wise-intent');

        $this->assertIsString($response);
        $this->assertNotSame('', $response);
    }
}
