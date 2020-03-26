<?php
/**
 */

namespace execut\crudFields\fields;


use execut\crudFields\Relation;
use execut\crudFields\TestCase;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

class FieldTest extends TestCase
{
    public function testGettersWithoutAttribute() {
        $field = new Field();
        $this->assertEquals([], $field->rules());
        $this->assertEquals([], $field->getColumn());
        $this->assertEquals([], $field->getField());
        $query = $this->getMockBuilder(ActiveQuery::class)->setConstructorArgs([Model::class])->getMock();
        $this->assertEquals($query, $field->applyScopes($query));
    }

    public function testGetColumns() {
        $column = [
            'class' => 'test',
        ];
        $field = new Field([
            'column' => $column,
            'attribute' => 'name'
        ]);
        $this->assertEquals([
            'name' => [
                'class' => 'test',
                'attribute' => 'name',
                'label' => 'Name'
            ],
        ], $field->columns);
    }

    public function testGetFields() {
        $formField = [
            'class' => 'test'
        ];
        $field = new Field([
            'field' => $formField,
            'attribute' => 'name'
        ]);
        $this->assertEquals([
            'name' => [
                'class' => 'test',
                'attribute' => 'name',
            ],
        ], $field->getFields());
    }

    public function testGetFieldConfig() {
        $formField = [
            'class' => 'test'
        ];
        $field = new Field([
            'field' => $formField,
            'attribute' => 'name'
        ]);
        $this->assertEquals($formField, $field->getFieldConfig());
    }

    public function testSetFieldConfigFromConstructor() {
        $formField = [
            'class' => 'test'
        ];
        $field = new Field([
            'fieldConfig' => $formField,
        ]);
        $this->assertEquals($formField, $field->getFieldConfig());
    }

    public function testGetReadOnlyByDefault() {
        $field = new Field();
        $this->assertFalse($field->getReadOnly());
    }

    public function testSetReadOnlyFromConstructor() {
        $field = new Field([
            'readOnly' => true,
        ]);
        $this->assertTrue($field->getReadOnly());
    }

    public function testSetReadOnly() {
        $field = new Field();
        $this->assertEquals($field, $field->setReadOnly(true));
        $this->assertTrue($field->getReadOnly());
    }

    public function testGetDisplayOnlyByDefault() {
        $field = new Field();
        $this->assertFalse($field->getDisplayOnly());
    }

    public function testSetDisplayOnlyFromConstructor() {
        $field = new Field([
            'displayOnly' => true,
        ]);
        $this->assertTrue($field->getDisplayOnly());
    }

    public function testSetDisplayOnly() {
        $field = new Field();
        $this->assertEquals($field, $field->setDisplayOnly(true));
        $this->assertTrue($field->getDisplayOnly());
    }

    public function testGetReadOnlyTrueWhenDisplayOnlyIsTrue() {
        $field = new Field([
            'displayOnly' => true,
        ]);
        $this->assertTrue($field->getReadOnly());
    }

//    public function testDisplayOnlyWithNotReadOnlyFlagException() {
//        $field = new Field();
//        $this->assertExceptionMessage('displayOnly is must bee true when readOnly true');
//        $field->setDisplayOnly(false);
//        $field->setReadOnly(false);
//    }

    public function testSetFieldConfigDirectly() {
        $formField = [
            'class' => 'test'
        ];
        $field = new Field([
        ]);
        $this->assertEquals($field, $field->setFieldConfig($formField));
        $this->assertEquals($formField, $field->getFieldConfig());
    }

    public function testApplyScopes() {
        $model = new Model;
        $q = $this->getMockBuilder(ActiveQuery::class)->setConstructorArgs([
            'a',
        ]) ->getMock();
        $q->expects($this->once())->method('andFilterWhere')->with([
            'test_model_table.name' => 'test'
        ])->will($this->returnValue($q));

        $field = new Field([
            'model' => $model,
            'attribute' => 'name',
        ]);
        $this->assertEquals($q, $field->applyScopes($q));
    }

    public function testApplyScopesWithNullValue() {
        $model = new Model;
        $q = $this->getMockBuilder(ActiveQuery::class)->setConstructorArgs([
            'a',
        ]) ->getMock();
        $q->expects($this->never())->method('andFilterWhere');

        $model->name = null;
        $field = new Field([
            'model' => $model,
            'attribute' => 'name',
        ]);
        $this->assertEquals($q, $field->applyScopes($q));
    }

    public function testApplyScopesWithEmptyValue() {
        $model = new Model;
        $q = $this->getMockBuilder(ActiveQuery::class)->setConstructorArgs([
            'a',
        ]) ->getMock();
        $q->expects($this->never())->method('andFilterWhere');

        $model->name = '';
        $field = new Field([
            'model' => $model,
            'attribute' => 'name',
        ]);
        $this->assertEquals($q, $field->applyScopes($q));
    }

    public function testApplyScopesWithRelationObject() {
        $relation = $this->getMockBuilder(Relation::class)->getMock();
        $relation->expects($this->once())->method('applyScopes');
        $field = new Field([
            'relation' => 'test',
            'relationObject' => $relation,
        ]);

        $field->applyScopes(new ActiveQuery('asdas'));
    }

    public function testApplyScopesWithScopeCallback() {
        $query = $this->getMockBuilder(ActiveQuery::class)->setConstructorArgs(['model class'])->getMock();
        $query->expects($this->once())->method('andWhere');
        $field = new Field([
            'relation' => 'test',
            'scope' => function ($q) {
                $q->andWhere('true=false');
            }
        ]);

        $field->applyScopes($query);
    }

    public function testApplyScopesWithScopeFalseCallback() {
        $model = new Model();
        $model->name = 'test';

        $relation = $this->getMockBuilder(Relation::class)->getMock();
        $relation->expects($this->never())->method('applyScopes');

        $query = $this->getMockBuilder(ActiveQuery::class)->setConstructorArgs(['model class'])->getMock();
        $query->expects($this->once())->method('andWhere');
        $query->expects($this->never())->method('andFilterWhere');
        $field = new Field([
            'relation' => 'test',
            'model' => $model,
            'attribute' => 'name',
            'relationObject' => $relation,
            'scope' => function ($q) {
                $q->andWhere('true=false');
                return false;
            }
        ]);

        $field->applyScopes($query);
    }

    public function testGetRules() {
        $model = new Model();
        $field = new Field([
            'model' => $model,
            'attribute' => 'name',
        ]);
        $this->assertEquals([
            'nameSafeOnGrid' => [
                ['name'],
                'safe',
                'on' => Field::SCENARIO_GRID,
            ],
            'namesafeonFormAndDefault' => [
                ['name'],
                'safe',
                'on' => [Field::SCENARIO_FORM],
            ],
        ], $field->rules());
    }

    public function testGetRulesWhileRequired() {
        $model = new Model();
        $field = new Field([
            'model' => $model,
            'attribute' => 'name',
            'required' => true,
        ]);
        $this->assertEquals([
            'nameSafeOnGrid' => [
                ['name'],
                'safe',
                'on' => Field::SCENARIO_GRID,
            ],
            'namerequiredonFormAndDefault' => [
                ['name'],
                'required',
                'on' => [Field::SCENARIO_FORM,],
            ],
        ], $field->rules());
    }

    public function testGetRulesWhenReadOnly() {
        $model = new Model();
        $field = new Field([
            'model' => $model,
            'attribute' => 'name',
            'readOnly' => true,
        ]);
        $this->assertEquals([
            'nameSafeOnGrid' => [
                ['name'],
                'safe',
                'on' => Field::SCENARIO_GRID,
            ],
        ], $field->rules());
    }

    public function testGetRelationObject() {
        $model = new Model;
        $field = new Field([
            'relation' => 'relationName',
            'nameAttribute' => 'nameAttribute',
            'orderByAttribute' => 'orderByAttribute',
            'with' => ['testRelation'],
            'valueAttribute' => 'valueAttribute',
            'updateUrl' => ['test/test'],
            'url' => ['test/test'],
            'attribute' => 'attribute',
            'idAttribute' => 'id_attribute',
            'model' => $model,
            'columnRecordsLimit' => 10,
            'isHasRelationAttribute' => 'isHasRelationAttribute',
            'isNoRenderRelationLink' => true,
            'label' => 'label',
            'urlMaker' => 'test url maker',
        ]);
        $relationObject = $field->getRelationObject();
        $this->assertInstanceOf(Relation::class, $relationObject);
        $this->assertEquals([
            'field' => $field,
            'name' => 'relationName',
            'nameAttribute' => 'nameAttribute',
            'orderByAttribute' => 'orderByAttribute',
            'with' => ['testRelation'],
            'valueAttribute' => 'valueAttribute',
            'updateUrl' => ['test/test'],
            'url' => ['test/test'],
            'attribute' => 'attribute',
            'model' => $model,
            'columnRecordsLimit' => 10,
            'isHasRelationAttribute' => 'isHasRelationAttribute',
            'isNoRenderRelationLink' => true,
            'label' => 'label',
            'idAttribute' => 'id_attribute',
            'urlMaker' => 'test url maker',
        ], [
            'field' => $relationObject->field,
            'name' => $relationObject->name,
            'nameAttribute' => $relationObject->nameAttribute,
            'orderByAttribute' => $relationObject->orderByAttribute,
            'with' => $relationObject->with,
            'valueAttribute' => $relationObject->valueAttribute,
            'updateUrl' => $relationObject->updateUrl,
            'url' => $relationObject->url,
            'attribute' => $relationObject->attribute,
            'model' => $relationObject->model,
            'columnRecordsLimit' => $relationObject->columnRecordsLimit,
            'isHasRelationAttribute' => $relationObject->isHasRelationAttribute,
            'isNoRenderRelationLink' => $relationObject->isNoRenderRelationLink,
            'label' => $relationObject->label,
            'idAttribute' => $relationObject->idAttribute,
            'urlMaker' => $relationObject->urlMaker,
        ]);
    }
}

class Model extends ActiveRecord {
    public $id = 2;
    public $name = 'test';
    public $test_test_id = 2;
    public $testTest = null;
    public $testTests = null;
    public static $query = null;
    public static $subQuery = null;
    public static $hasManySubQuery = null;
    public $badAttribute = null;

    public static function primaryKey() {
        return ['id'];
    }

    public static function find()
    {
        return self::$query;
    }

    public function getTestTest()
    {
        if (self::$subQuery === null) {
            self::$subQuery = new ActiveQuery(Model::class);
        }

        return self::$subQuery;
    }

    public function getTestTests()
    {
        if (self::$hasManySubQuery === null) {
            self::$hasManySubQuery = new ActiveQuery(Model::class);
            self::$hasManySubQuery->multiple = true;
        }

        return self::$hasManySubQuery;
    }

    public function getRelation($name, $throwException = true)
    {
        if ($name === 'testTest') {
            return $this->getTestTest();
        } else if ($name === 'testTests') {
            return $this->getTestTests();
        } else {
            return false;
        }
    }

    public static function tableName()
    {
        return 'test_model_table';
    }

    public function __get($name)
    {
        if ($name === 'primaryKey') {
            return $this->id;
        }
        if ($name === 'badAttribute') {
            return;
        }

        return parent::__get($name);
    }

    public function hasProperty($name, $checkVars = true, $checkBehaviors = true)
    {
        if ($name === 'badAttribute') {
            return false;
        }

        return true;
    }
}