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
use craft\web\AssetManager;
use craft\web\View;
use lindemannrock\formieratingfield\fields\Rating;
use lindemannrock\formieratingfield\FormieRatingField;
use lindemannrock\formieratingfield\tests\TestCase;
use lindemannrock\formieratingfield\web\assets\field\RatingFieldAsset;

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
            self::assertSame(['js/rating.js'], $bundle->js);
        } finally {
            FormieRatingField::$plugin = $originalPlugin;
            Craft::$app->set('db', $originalDb);
            Craft::setAlias('@lindemannrock/formieratingfield', $originalAlias);
        }
    }

    public function testModuleUsesRegisteredBundleBaseUrlAndRegistrationIsDeduplicated(): void
    {
        $originalAssetManager = Craft::$app->getAssetManager();
        $originalView = Craft::$app->getView();
        $distPath = Craft::getAlias('@lindemannrock/formieratingfield/web/assets/field/dist');
        $baseUrl = 'https://cdn.example.test/formie-rating-field';
        $assetManager = new AssetManager([
            'basePath' => $distPath,
            'baseUrl' => $baseUrl,
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

            $field = new Rating([
                'ratingType' => Rating::RATING_TYPE_STAR,
                'ratingSize' => 'large',
                'minValue' => 1,
                'maxValue' => 5,
                'allowHalfRatings' => true,
                'showSelectedLabel' => true,
                'showEndpointLabels' => false,
                'singleEmojiSelection' => false,
            ]);

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
            self::assertSame(['js/rating.js'], $view->assetBundles[RatingFieldAsset::class]->js);
        } finally {
            Craft::$app->set('view', $originalView);
            Craft::$app->set('assetManager', $originalAssetManager);
        }
    }
}
