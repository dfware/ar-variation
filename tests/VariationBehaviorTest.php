<?php

namespace yii2tech\tests\unit\ar\variation;

use yii2tech\ar\variation\VariationBehavior;
use yii2tech\tests\unit\ar\variation\data\Article;
use yii2tech\tests\unit\ar\variation\data\Item;

class VariationBehaviorTest extends TestCase
{
    public function testGetVariationAttributes()
    {
        /* @var $item Item */

        $item = Item::findOne(1);

        $this->assertNotEmpty($item->title);
        $this->assertTrue($item->isRelationPopulated('defaultTranslation'));
        $this->assertEquals($item->defaultTranslation->title, $item->title);
        $this->assertNotEmpty($item->description);
        $this->assertEquals($item->defaultTranslation->description, $item->description);

        $item = Item::findOne(2);

        $this->assertNotEmpty($item->title);
        $this->assertEmpty($item->defaultTranslation);
        $this->assertNull($item->brief);
        $this->assertEquals('default', $item->summary);
        $this->expectException('yii\base\UnknownPropertyException');
        $item->description;
    }

    public function testGetVariationModels()
    {
        /* @var $item Item|VariationBehavior */

        $item = Item::findOne(1);

        $variationModels = $item->getVariationModels();
        $this->assertCount(2, $variationModels);
        $this->assertEquals($item->id, $variationModels[0]->itemId);
        $this->assertEquals($item->id, $variationModels[1]->itemId);
        $this->assertFalse($variationModels[0]->isNewRecord);
        $this->assertFalse($variationModels[1]->isNewRecord);

        $item = Item::findOne(2);
        $variationModels = $item->getVariationModels();
        $this->assertCount(2, $variationModels);
        $this->assertEquals($item->id, $variationModels[0]->itemId);
        $this->assertEquals($item->id, $variationModels[1]->itemId);
        $this->assertTrue($variationModels[0]->isNewRecord);
        $this->assertFalse($variationModels[1]->isNewRecord);
    }

    /**
     * @depends testGetVariationModels
     */
    public function testGetVariationModel()
    {
        /* @var $item Item|VariationBehavior */

        $item = Item::findOne(1);

        $variationModel = $item->getVariationModel(1);
        $this->assertTrue(is_object($variationModel));
        $this->assertEquals(1, $variationModel->languageId);

        $variationModel = $item->getVariationModel(2);
        $this->assertTrue(is_object($variationModel));
        $this->assertEquals(2, $variationModel->languageId);
    }

    /**
     * @depends testGetVariationModels
     */
    public function testValidate()
    {
        /* @var $item Item|VariationBehavior */

        $item = new Item();
        $item->name = 'new item';
        $this->assertTrue($item->validate());

        $variationModels = $item->getVariationModels();
        $this->assertFalse($item->validate());

        foreach ($variationModels as $variationModel) {
            $variationModel->title = 'new title';
            $variationModel->description = 'new description';
        }
        $this->assertTrue($item->validate());
    }

    /**
     * @depends testGetVariationModels
     */
    public function testSave()
    {
        /* @var $item Item|VariationBehavior */

        $item = new Item();
        $item->name = 'new item';

        foreach ($item->getVariationModels() as $variationModel) {
            $variationModel->title = 'new title';
            $variationModel->description = 'new description';
        }

        $item->save(false);

        $item = Item::findOne($item->id);
        $this->assertCount(2, $item->translations);
    }

    /**
     * @depends testGetVariationModels
     */
    public function testOptionQueryFilter()
    {
        /* @var $item Item|VariationBehavior */

        $item = new Item();
        $this->assertCount(2, $item->getVariationModels());

        $item = new Item();
        $item->optionQueryFilter = ['id' => 2];
        $this->assertCount(1, $item->getVariationModels());
        $this->assertEquals(2, $item->getVariationModels()[0]->languageId);

        $item = new Item();
        $item->optionQueryFilter = function ($query) {
            $query->andWhere(['id' => 1]);
        };
        $this->assertCount(1, $item->getVariationModels());
        $this->assertEquals(1, $item->getVariationModels()[0]->languageId);
    }

    public function testInitWithWrongConfig()
    {
        $this->expectException('yii\base\UnknownPropertyException');

        $behavior = new VariationBehavior([
            'unExistingProperty' => 'any'
        ]);
    }

    /**
     * @depends testSave
     */
    public function testVariationSaveFilter()
    {
        /* @var $item Item|VariationBehavior */

        $item = new Item();
        $item->name = 'new item';

        foreach ($item->getVariationModels() as $variationModel) {
            $variationModel->title = 'new title';
            $variationModel->description = 'new description';
        }

        $item->variationSaveFilter = function ($model) {
            return ($model->languageId != 1);
        };
        $item->save(false);

        $item = Item::findOne($item->id);
        $this->assertCount(1, $item->translations);
        $this->assertEquals(2, $item->translations[0]->languageId);

        foreach ($item->getVariationModels() as $variationModel) {
            $variationModel->title = 'new title';
            $variationModel->description = 'new description';
        }

        $item->variationSaveFilter = function ($model) {
            return ($model->languageId != 2);
        };
        $item->save(false);

        $item = Item::findOne($item->id);
        $this->assertCount(1, $item->translations);
        $this->assertEquals(1, $item->translations[0]->languageId);
    }

    /**
     * @depends testGetVariationModels
     */
    public function testDefaultVariationModelAttributes()
    {
        /* @var $behavior VariationBehavior */

        $model = new Article();

        $behavior = $model->getBehavior('censoredContent');
        $variationModels = $behavior->getVariationModels();
        $this->assertEquals('censored', $variationModels[0]->censorType);

        $behavior = $model->getBehavior('uncensoredContent');
        $variationModels = $behavior->getVariationModels();
        $this->assertEquals('no', $variationModels[0]->censorType);

        $behavior = $model->getBehavior('callbackContent');
        $variationModels = $behavior->getVariationModels();
        $this->assertEquals('callback', $variationModels[0]->censorType);
    }

    public function testLeftJoinWith()
    {
        $this->assertTrue(true);

        $totalItemCount = (int)Item::find()->count();
        $items = Item::find()->joinWith('defaultTranslation')->all();

        $this->assertCount($totalItemCount, $items);
        $this->assertTrue($items[0]->isRelationPopulated('defaultTranslation'));
    }

    /**
     * @depends testGetVariationAttributes
     */
    public function testSetVariationAttributes()
    {
        /* @var $model Item */
        $model = Item::findOne(1);

        $title = 'new title';
        $model->title = $title;
        $this->assertEquals($title, $model->defaultTranslation->title);

        $model = new Item();

        $title = 'new model title';
        $model->title = $title;
        $this->assertEquals($title, $model->defaultTranslation->title);
        $this->assertNotEmpty($model->defaultTranslation->languageId);
    }

    /**
     * @depends testValidate
     * @depends testSetVariationAttributes
     */
    public function testValidateDefaultVariationModel()
    {
        $model = new Item();
        $model->name = 'new item';

        $model->title = 'new title';
        $this->assertFalse($model->validate());

        $model->title = 'new title';
        $model->description = 'new description';
        $this->assertTrue($model->validate());
    }

    /**
     * @depends testSave
     * @depends testValidateDefaultVariationModel
     */
    public function testSaveDefaultVariationModel()
    {
        $model = new Item();
        $model->name = 'new item';
        $model->title = 'new title';
        $model->description = 'new description';

        $model->save(false);

        $model = Item::findOne($model->id);
        $this->assertCount(1, $model->translations);

        $this->assertEquals('new title', $model->title);

        $model = new Item();
        $model->name = 'new item';
        $model->defaultTranslation; // initialize empty variation
        $this->assertTrue($model->validate()); // no error
        $this->assertTrue($model->save(false)); // no error
    }

    /**
     * @see https://github.com/yii2tech/ar-variation/issues/24
     *
     * @depends testLeftJoinWith
     */
    public function testLeftJoinWithAmbiguousColumn()
    {
        /* @var $items Item[] */

        $items = Item::find()
            ->joinWith([
                'defaultTranslation',
                'category' => function ($query) {
                    $query->innerJoinWith('defaultTranslation');
                },
            ])
            ->all();

        $this->assertCount(2, $items);
    }
}