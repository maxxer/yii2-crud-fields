<?php
/**
 * Created by PhpStorm.
 * User: execut
 * Date: 6/28/17
 * Time: 10:48 AM
 */

namespace execut\crudFields;


use execut\crudFields\fields\Field;
use yii\base\BaseObject;
use yii\base\Exception;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Inflector;
use yii\helpers\Url;

class Relation extends BaseObject
{
    /**
     * @var Field
     */
    public $field = null;
    public $nameAttribute = 'name';
    public $valueAttribute = null;
    public $with = null;
    protected $_name = null;
    public function setName($relation) {
        $this->_name = $relation;

        return $this;
    }

    protected function getPrimaryKey() {
        return current($this->field->model::primaryKey());
    }

    public function getName() {
        if ($this->_name === null) {
            $this->_name = $this->getRelationNameFromAttribute();
        }

        return $this->_name;
    }

    public function getWith() {
        if ($this->with === null) {
            return $this->getName();
        }

        return $this->with;
    }

    public function applyScopes(ActiveQuery $query)
    {
        if ($this->getRelationQuery()->multiple) {
            $value = $this->field->value;
            if (!empty($value)) {
                if ($this->isVia()) {
                    $pk = $this->getPrimaryKey();
                } else {
                    $pk = $this->getRelationPrimaryKey();
                }

                if (is_array($value) && current($value) instanceof ActiveRecord) {
                    $relationPk = $this->getRelationPrimaryKey();
                    $value = ArrayHelper::map($value, $relationPk, $relationPk);
                    $value = array_values($value);
                }

                if ($this->isVia()) {
                    $viaRelationQuery = $this->getViaRelationQuery();
                    $viaRelationQuery->select(key($viaRelationQuery->link))
                        ->andWhere([
                            current($this->getRelationQuery()->link) => $value,
                        ]);
                    $viaRelationQuery->link = null;
                    $viaRelationQuery->primaryModel = null;
                } else {
                    $viaRelationQuery = $this->getRelationQuery();
                    $viaRelationQuery->select(key($viaRelationQuery->link));
                    $viaRelationQuery->indexBy = key($viaRelationQuery->link);
                    $viaRelationQuery->andWhere([
                        current($viaRelationQuery->link) => $value
                    ]);
                    $viaRelationQuery->link = null;
                    $viaRelationQuery->primaryModel = null;
                }

                $query->andWhere([
                    $pk => $viaRelationQuery,
                ]);
            }
        }

        if ($this->getWith()) {
            $query->with($this->getWith());
        }

        return $query; // TODO: Change the autogenerated stub
    }

    protected function getRelationNameFromAttribute() {
        $attribute = $this->field->attribute;
        $relationName = lcfirst(Inflector::id2camel(str_replace('_id', '', $attribute), '_'));

        return $relationName;
    }

    /**
     * @return string
     */
    public function getSourceText()
    {
        $result = $this->getSourcesText();

        return current($result);
    }

    public function getRelationModelClass() {
        $modelClass = $this->getRelationQuery()->modelClass;

        return $modelClass;
    }

    public function getRelationFormName() {
        $model = $this->getRelationModel();

        return $model->formName();
    }

    public function getRelationPrimaryKey() {
        $relationQuery = $this->getRelationQuery();
        $class = $relationQuery->modelClass;
        return current($class::primaryKey());
    }

    /**
     * @return array
     */
    public function getSourcesText(): array
    {
        $sourceInitText = [];
        $nameAttribute = $this->nameAttribute;
        $model = $this->field->model;
        $modelClass = $this->getRelationModelClass();
        if (empty($this->field->value)) {
            return [];
        }

        if ($this->isManyToMany()) {
            $relationQuery = $this->getRelationQuery();
            $via = $relationQuery->via;
            if ($via instanceof ActiveQuery) {
                /**
                 * @todo Needed autodetect via PK
                 */
                $sourceIds = $via->select('id');
            } else {
                $viaRelationName = $via[0];
                $viaModels = $this->field->model->$viaRelationName;
                $viaAttribute = $this->field->attribute;
                if (!empty($this->field->model->$viaAttribute)) {
                    $sourceIds = $this->field->model->$viaAttribute;
                    foreach ($sourceIds as $key => $sourceId) {
                        if ($sourceId instanceof ActiveRecord) {
                            $sourceIds[$key] = $sourceId->primaryKey;
                        }
                    }
                } else {
                    $sourceIds = [];
                    foreach ($viaModels as $viaModel) {
                        $sourceIds[$viaModel->$viaAttribute] = $viaModel->$nameAttribute;
                    }
                }
            }
        } else {
            $attribute = $this->field->attribute;
            if (!empty($model->$attribute)) {
                $sourceIds = [];
                if (is_array($model->$attribute)) {
                    $sourceIds = $model->$attribute;
                } else {
                    $sourceIds[] = $model->$attribute;
                }

                foreach ($sourceIds as $key => $sourceId) {
                    if ($sourceId instanceof ActiveRecord) {
                        $sourceInitText[$sourceId->primaryKey] = $sourceId->$nameAttribute;
                    }
                }

                if (!empty($sourceInitText)) {
                    return $sourceInitText;
                }
            }
        }

        if (!empty($sourceIds)) {
            $pk = current($modelClass::primaryKey());
            $q = $modelClass::find()->andWhere([$pk => $sourceIds]);
            $models = $q->all();
            $sourceInitText = ArrayHelper::map($models, $pk, $nameAttribute);
        }

        return $sourceInitText;
    }

    /**
     * @param $relationName
     * @param $model
     * @param $nameAttribute
     * @return array
     */
    public function getData(): array
    {
        $data = ['' => ''];

        $models = $this->getRelatedModels();

        $relationQuery = $this->getRelationQuery();
        $idAttribute = key($relationQuery->link);

        $data = ArrayHelper::merge($data, ArrayHelper::map($models, $idAttribute, $this->nameAttribute));
        return $data;
    }

    public function isVia() {
        return $this->getRelationQuery()->via !== null;
    }

    public function getColumnValue($row) {
        if (!$this->isHasMany()) {
            if ($this->valueAttribute !== null) {
                $attribute = $this->valueAttribute;
            } else {
                $attribute = $this->name . '.' . $this->nameAttribute;
            }

//            $q = $this->getRelationQuery();
//            $fromAttribute = current($q->link);
//            if (empty($this->field->model->$fromAttribute)) {
//                return;
//            }

            $value = ArrayHelper::getValue($row, $attribute);
            if (!$value) {
                return;
            }

            if ($this->field->isNoRenderRelationLink || $this->field->url === null) {
                return $value;
            }

            $url = $this->field->url;
            if (!is_array($url)) {
                $url = [$url];
            } else {
                $url[0] = str_replace('/index', '', $url[0]) . '/update';
            }

            if (!array_key_exists('id', $url)) {
                $attribute = $this->field->attribute;
                $url['id'] = $row->$attribute;
            }

            return $value . '&nbsp;' . Html::a('>>>', Url::to($url), ['title' => $this->field->getLabel() . ' - перейти к редактированию']);
        } else {
            $models = $row->{$this->getName()};
            $result = '';
            $nameAttribute = $this->nameAttribute;
            foreach ($models as $model) {
                $value = $model->$nameAttribute;
                if ($this->field->isNoRenderRelationLink || $this->field->url === null) {
                    $result .= $value . '<br>';
                    continue;
                }

                $url = $this->field->url;
                if (is_array($url)) {
                    $url = $url[0];
                } else {
                    $url = str_replace('/index', '', $url);
                }

                $url = [$url . '/update', 'id' => $model->primaryKey];

                $result .= $value . '&nbsp;' . Html::a('>>>', Url::to($url), ['title' => $this->field->getLabel() . ' - перейти к редактированию']) . '<br>';
            }

            return $result;
        }
    }

    protected function isManyToMany() {
        $relationQuery = $this->getRelationQuery();

        return $relationQuery->multiple && $this->isVia();
    }

    /**
     * @return ActiveQuery
     */
    public function getRelationQuery()
    {
        $relationQuery = $this->field->model->getRelation($this->getName());

        return $relationQuery;
    }

    /**
     * @return mixed
     */
    public function getViaRelation()
    {
        $relationQuery = $this->getRelationQuery();

        $via = $relationQuery->via;
        if ($via instanceof ActiveQuery) {
            return $via;
        }

        $viaRelation = $via[0];
        return $viaRelation;
    }

    /**
     * @return mixed
     */
    public function getViaRelationQuery()
    {
        $viaRelation = $this->getViaRelation();
        $viaRelationQuery = $this->field->model->getRelation($viaRelation);
        return $viaRelationQuery;
    }

    public function getViaFromAttribute() {
        return key($this->getViaRelationQuery()->link);
    }

    public function getViaToAttribute() {
        return current($this->getRelationQuery()->link);
    }

    public function getViaRelationModelClass() {
        return $this->getViaRelationQuery()->modelClass;
    }

    /**
     * @return mixed
     */
    public function getRelatedModels()
    {
        $relationQuery = clone $this->getRelationQuery();
        $relationQuery->link = null;
        $relationQuery->primaryModel = null;

        if ($this->nameAttribute !== null) {
            $relationQuery->orderBy($this->nameAttribute);
        }

        $models = $relationQuery->all();
        return $models;
    }

    public function isHasMany() {
        return $this->getRelationQuery()->multiple;
    }
    
    public function getRelationFields() {
        $model = $this->getRelationModel();
        if (!$model->getBehavior('fields') || $this->isManyToMany() || $this->isHasMany()) {
            return [];
        }

        $fields = $model->getFields();
        $pks = $model->primaryKey();
        foreach ($fields as $key => $field) {
            if (!$field->isRenderInRelationForm) {
                unset($fields[$key]);
            }

            if ($field->attribute === null || in_array($key, $pks)) {
                unset($fields[$key]);
            }
        }

        /**
         * TODO copy-paste from Behavior sort logic
         */
        uasort($fields, function ($a, $b) {
            return $a->order > $b->order;
        });

        return $fields;
    }

    /**
     * @return mixed
     */
    public function getRelationModel($isFirst = false)
    {
        $name = $this->getName();
        if ((!$this->isHasMany() || $isFirst) && ($model = $this->field->model->$name)) { //$this->field->getValue() &&
            if ($isFirst) {
                if (current($model)) {
                    return current($model);
                }
            } else {
                return $model;
            }
        }

        $relationModelClass = $this->getRelationModelClass();
        $model = new $relationModelClass;

        return $model;
    }
}