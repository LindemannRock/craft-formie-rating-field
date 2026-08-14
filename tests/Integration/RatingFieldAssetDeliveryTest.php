<?php
/**
 * LindemannRock Formie Rating Field
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\formieratingfield\tests\Integration;

use Craft;
use craft\db\Connection;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\web\AssetManager;
use craft\web\View;
use lindemannrock\formieratingfield\fields\Rating;
use lindemannrock\formieratingfield\FormieRatingField;
use lindemannrock\formieratingfield\tests\TestCase;
use lindemannrock\formieratingfield\web\assets\field\RatingFieldAsset;
use verbb\formie\elements\Form;
use verbb\formie\models\FieldLayout;

/**
 * Covers static asset delivery for front-end rating fields.
 *
 * @since 3.22.0
 */
final class RatingFieldAssetDeliveryTest extends TestCase
{
    public function testBundleInitializesFromComposerAliasWithoutPluginOrDatabaseState(): void
    {
        $originalAlias = Craft::getAlias('@lindemannrock/formieratingfield');
        $originalDb = Craft::$app->getDb();
        $originalPlugin = FormieRatingField::$plugin;
        $aliasRoot = sys_get_temp_dir() . '/formie-rating-field-asset-alias-' . bin2hex(random_bytes(8));
        $offlineDb = new Connection([
            'dsn' => 'unsupported:formie-rating-field-asset-test',
        ]);

        try {
            Craft::setAlias('@lindemannrock/formieratingfield', $aliasRoot);
            Craft::$app->set('db', $offlineDb);
            FormieRatingField::$plugin = null;

            $bundle = new RatingFieldAsset();

            self::assertFalse($offlineDb->getIsActive());
            self::assertSame($aliasRoot . '/web/assets/field/dist', $bundle->sourcePath);
            self::assertSame(['css/rating.css'], $bundle->css);
            self::assertSame([], $bundle->js);
        } finally {
            FormieRatingField::$plugin = $originalPlugin;
            Craft::$app->set('db', $originalDb);
            Craft::setAlias('@lindemannrock/formieratingfield', $originalAlias);
        }
    }

    public function testFormieModuleOwnsFinalScriptDeliveryAcrossRepeatedFields(): void
    {
        $originalAssetManager = Craft::$app->getAssetManager();
        $originalView = Craft::$app->getView();
        $distPath = Craft::getAlias('@lindemannrock/formieratingfield/web/assets/field/dist');
        $baseUrl = 'https://cdn.example.test/formie-rating-field';
        $assetManager = new AssetManager([
            'basePath' => $distPath,
            'baseUrl' => $baseUrl,
            'appendTimestamp' => true,
            'bundles' => [
                RatingFieldAsset::class => [
                    'basePath' => $distPath,
                    'baseUrl' => $baseUrl,
                ],
            ],
        ]);
        $view = new View();

        try {
            Craft::$app->set('assetManager', $assetManager);
            Craft::$app->set('view', $view);

            $fieldSettings = [
                'ratingType' => Rating::RATING_TYPE_STAR,
                'ratingSize' => 'large',
                'minValue' => 1,
                'maxValue' => 5,
                'allowHalfRatings' => true,
                'showSelectedLabel' => true,
                'showEndpointLabels' => false,
                'singleEmojiSelection' => false,
            ];
            $field = new Rating(['handle' => 'firstRating', ...$fieldSettings]);
            $repeatedField = new Rating(['handle' => 'secondRating', ...$fieldSettings]);

            $module = $field->getFrontEndJsModules();
            $secondModule = $field->getFrontEndJsModules();

            self::assertSame($module, $secondModule);
            self::assertSame($baseUrl . '/js/rating.js', $module['src']);
            self::assertSame('FormieRating', $module['module']);
            self::assertSame([
                'ratingType' => Rating::RATING_TYPE_STAR,
                'ratingSize' => 'large',
                'minValue' => 1,
                'maxValue' => 5,
                'allowHalfRatings' => true,
                'showSelectedLabel' => true,
                'showEndpointLabels' => false,
                'singleEmojiSelection' => false,
            ], $module['settings']);

            self::assertSame([RatingFieldAsset::class], array_keys($view->assetBundles));
            self::assertInstanceOf(RatingFieldAsset::class, $view->assetBundles[RatingFieldAsset::class]);
            self::assertSame($baseUrl, $view->assetBundles[RatingFieldAsset::class]->baseUrl);
            self::assertSame(['css/rating.css'], $view->assetBundles[RatingFieldAsset::class]->css);
            self::assertSame([], $view->assetBundles[RatingFieldAsset::class]->js);

            $form = new Form([
                'handle' => 'assetDelivery',
                'title' => 'Asset Delivery',
            ]);
            $form->setFormId('fui-asset-delivery');
            $form->setFormLayout(new FieldLayout([
                'pages' => [[
                    'label' => 'Page 1',
                    'settings' => [],
                    'rows' => [[
                        'fields' => [$field, $repeatedField],
                    ]],
                ]],
            ]));

            $formConfig = $form->getFrontEndJsVariables();
            $repeatedRenderConfig = $form->getFrontEndJsVariables();
            $expectedRegisteredJs = [[
                'src' => $baseUrl . '/js/rating.js',
                'module' => 'FormieRating',
            ]];

            self::assertSame($expectedRegisteredJs, $formConfig['registeredJs']);
            self::assertSame($expectedRegisteredJs, $repeatedRenderConfig['registeredJs']);

            $headHtml = $view->getHeadHtml(false);
            $bodyHtml = $view->getBodyHtml(false);
            $formHtml = Html::tag('form', '', [
                'data-fui-form' => Json::encode($formConfig),
            ]);
            $finalHtml = $headHtml . $formHtml . $bodyHtml;
            $decodedHtml = str_replace('\/', '/', html_entity_decode($finalHtml));

            self::assertSame(1, preg_match_all('/<link[^>]+href="[^"]*rating\.css(?:\?[^"]*)?"[^>]*>/', $headHtml), $headHtml);
            self::assertSame(0, preg_match_all('/<script[^>]+src="[^"]*rating\.js(?:\?[^"]*)?"[^>]*><\/script>/', $headHtml . $bodyHtml), $finalHtml);
            self::assertSame(1, substr_count($decodedHtml, $baseUrl . '/js/rating.js'), $finalHtml);
            self::assertSame(0, preg_match_all('/rating\.js\?[^&"<]+/', $decodedHtml), $finalHtml);
        } finally {
            $view->clear();
            Craft::$app->set('view', $originalView);
            Craft::$app->set('assetManager', $originalAssetManager);
        }
    }

    public function testCustomerArchivesIncludePackagedRatingAssets(): void
    {
        $expected = [
            'src/web/assets/field/dist/css/rating.css',
            'src/web/assets/field/dist/js/rating.js',
        ];
        $packageRoot = dirname(__DIR__, 2);
        $archiveRoot = $this->createTrackedTempDirectory('formie-rating-field-asset-archive-');
        $composerHome = $archiveRoot . '/composer-home';
        $gitArchive = $archiveRoot . '/git-package.tar';
        $composerArchive = $archiveRoot . '/composer-package.tar';

        self::assertTrue(mkdir($composerHome));

        foreach ($expected as $path) {
            self::assertFileExists($packageRoot . '/' . $path, $path);
        }

        $trackedFiles = $this->runProcess([
            'git',
            '-c',
            'safe.directory=' . $packageRoot,
            'ls-files',
            '--error-unmatch',
            '--',
            ...$expected,
        ], $packageRoot);
        $this->runProcess([
            'git',
            '-c',
            'safe.directory=' . $packageRoot,
            'archive',
            '--worktree-attributes',
            '--output=' . $gitArchive,
            'HEAD',
        ], $packageRoot);
        $this->runProcess([
            '/usr/bin/env',
            'COMPOSER_HOME=' . $composerHome,
            'composer',
            'archive',
            '--format=tar',
            '--dir=' . $archiveRoot,
            '--file=composer-package',
            '--no-interaction',
            '--no-ansi',
        ], $packageRoot);

        $gitMembers = array_filter(explode("\n", $this->runProcess(['tar', '-tf', $gitArchive], $packageRoot)));
        $composerMembers = array_filter(explode("\n", $this->runProcess(['tar', '-tf', $composerArchive], $packageRoot)));

        foreach ($expected as $path) {
            self::assertStringContainsString($path, $trackedFiles);
            self::assertContains($path, $gitMembers, 'Git archive: ' . $path);
            self::assertContains($path, $composerMembers, 'Composer archive: ' . $path);
        }
    }

    /**
     * @param list<string> $command
     */
    private function runProcess(array $command, string $workingDirectory): string
    {
        $pipes = [];
        $process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $workingDirectory,
        );
        self::assertIsResource($process);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        self::assertIsString($output);
        self::assertIsString($error);
        self::assertSame(0, proc_close($process), $error);

        return $output;
    }
}
