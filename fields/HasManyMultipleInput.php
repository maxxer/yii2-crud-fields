<?php
/**
 */

namespace execut\crudFields\fields;


use detalika\clients2\models\Contacts;
use execut\oData\ActiveRecord;
use kartik\detail\DetailView;
use kartik\grid\BooleanColumn;
use kartik\grid\GridView;
use kartik\select2\Select2;
use unclead\multipleinput\MultipleInput;
use unclead\multipleinput\MultipleInputColumn;
use yii\data\ActiveDataProvider;
use yii\data\ArrayDataProvider;
use yii\db\ActiveQuery;
use yii\db\pgsql\Schema;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\JsExpression;

class HasManyMultipleInput extends Field
{
    public $url = null;
    public $gridOptions = [];
    public $columns = [
        'id' => [
            'attribute' => 'id',
        ],
        'name' => [
            'attribute' => 'name',
        ],
        'visible' => [
            'class' => BooleanColumn::class,
            'attribute' => 'visible'
        ],
    ];

    public $mainAttribute = 'name';

    public $isGridInColumn = false;

    public $toAttribute = null;

    public $viaColumns = [
    ];

    public $isGridForOldRecords = false;

    public function getFields($isWithRelationsFields = true)
    {
        if ($this->isGridForOldRecords && !$this->model->isNewRecord) {
            return [
                $this->attribute . 'Group' => [
                    'group' => true,
                    'label' => $this->getLabel(),
                    'groupOptions' => [
                        'class' => 'success',
                    ],
                ],
                $this->attribute => $this->getGrid(),
            ];
        }

        return parent::getFields($isWithRelationsFields); // TODO: Change the autogenerated stub
    }

    public function getField()
    {
        $field = parent::getField();
        if (!is_array($field)) {
            return $field;
        }

        $widgetOptions = $this->getMultipleInputWidgetOptions();

        $attribute = $this->relation;
        return ArrayHelper::merge([
            'type' => DetailView::INPUT_WIDGET,
            'attribute' => $attribute,
//            'label' => $this->getLabel(),
            'format' => 'raw',
            'value' => function () {
                $dataProvider = new ActiveDataProvider();
//                $query = $this->model->getRelation($this->relation);
//                $dataProvider->query = $query;
//                return GridView::widget([
//                    'dataProvider' => $dataProvider,
//                    'columns' => $this->columns,
//                ]);
            },
            'widgetOptions' => $widgetOptions,
        ], $field);
    }

    protected function getGrid() {
        return [
            'attribute' => $this->attribute,
            'format' => 'raw',
            'displayOnly' => true,
//            'labelColOptions' => [
//                'style' => 'display:none',
//            ],
//            'valueColOptions' => [
//                'colspan' => 2,
//                'style' => [
//                    'padding' => 0,
//                ],
//            ],
            'group' => true,
            'groupOptions' => [
                'style' => [
                    'padding' => 0,
                ],
            ],
            'label' => function () {
                $model = $this->model;
                $relationName = $this->getRelationObject()->getName();
                $dataProvider = new ActiveDataProvider([
                    'query' => $model->getRelation($relationName),
                ]);
//                $models = $model->$relationName;
//                $models = ArrayHelper::map($models, function ($row) {
//                    return $row->primaryKey;
//                }, function ($row) {
//                    return $row;
//                });
//                $dataProvider = new ArrayDataProvider([
//                    'allModels' => $models,
//                ]);
                $widgetClass = GridView::class;
                $gridOptions = $this->gridOptions;
                if (is_callable($gridOptions)) {
                    $gridOptions = $gridOptions();
                }

                if (!empty($gridOptions['class'])) {
                    $widgetClass = $gridOptions['class'];
                }

                return $widgetClass::widget(ArrayHelper::merge([
                    'dataProvider' => $dataProvider,
                    'layout' => '{toolbar}{summary}{items}{pager}',
                    'bordered' => false,
                    'toolbar' => '',
//                    'caption' => $this->getLabel(),
//                    'captionOptions' => [
//                        'class' => 'success',
//                    ],
                    'columns' => $this->getRelationObject()->getRelationModel()->getGridColumns(),
                    'showOnEmpty' => true,
                ], $gridOptions));
            },
        ];
    }

    public $nameParam = null;

    /**
     * @TODO Copy past from HasOneSelect2
     *
     * @return null|string
     */
    public function getNameParam() {
        if ($this->nameParam !== null) {
            return $this->nameParam;
        }

        $formName = $this->getRelationObject()->getRelationFormName();

        return $formName . '[' . $this->nameAttribute . ']';
    }

    public function applyScopes(ActiveQuery $query)
    {
        if ($this->scope === false) {
            return $query;
        }

        if (!empty($this->model->errors)) {
            return $query->andWhere('false');
        }

        $relatedModelClass = $this->getRelationObject()->getRelationModelClass();
        $relatedModel = new $relatedModelClass;
        foreach ($this->value as $row) {
            $row = array_filter($row->attributes);
            if (!empty($row) && !empty($row[$this->mainAttribute])) {
                $relatedModel->attributes = $row;

                $relationQuery = $this->getRelationObject()->getRelationQuery();
                $relationQuery = $relatedModel->applyScopes($relationQuery);

                $relationQuery->select(key($relationQuery->link));
                $relationQuery->indexBy = key($relationQuery->link);


                if (!($this->model instanceof ActiveRecord)) {
                    $attributePrefix = $this->model->tableName() . '.';
                } else {
                    $attributePrefix = '';
                }

                $relatedAttribute = current($relationQuery->link);
                $relationQuery->link = null;
                $relationQuery->primaryModel = null;

                $query->andWhere([
                    $attributePrefix . $relatedAttribute => $relationQuery,
                ]);
            }
        }

        if ($this->columnRecordsLimit === null) {
            $query->with($this->getRelationObject()->getWith());
        }

//        return $query;
        return $query;
    }


    public function getColumn() {
        $column = parent::getColumn();
        if ($column === false) {
            return false;
        }

//        $sourceInitText = $this->getRelationObject()->getSourcesText();

//        $sourcesNameAttribute = $modelClass::getFormAttributeName('name');
        if ($this->isGridInColumn) {
            $valueClosure = function ($row) {
                $relationName = $this->getRelationObject()->getName();
                $dataProvider = new ActiveDataProvider([
                    'query' => $row->getRelation($relationName)->limit(10),
                ]);
                //                $models = $model->$relationName;
                //                $models = ArrayHelper::map($models, function ($row) {
                //                    return $row->primaryKey;
                //                }, function ($row) {
                //                    return $row;
                //                });
                //                $dataProvider = new ArrayDataProvider([
                //                    'allModels' => $models,
                //                ]);
                $widgetClass = GridView::class;
                if (!empty($this->gridOptions['class'])) {
                    $widgetClass = $this->gridOptions['class'];
                }

                return $widgetClass::widget(ArrayHelper::merge([
                    'dataProvider' => $dataProvider,
                    'layout' => '{toolbar}{summary}{items}{pager}',
                    'bordered' => false,
                    'toolbar' => '',
                    //                    'caption' => $this->getLabel(),
                    //                    'captionOptions' => [
                    //                        'class' => 'success',
                    //                    ],
                    'columns' => $this->getRelationObject()->getRelationModel()->getGridColumns(),
                    'showOnEmpty' => true,
                ], $this->gridOptions));
            };
        } else {
            $valueClosure = function ($row) {
                return $this->getRelationObject()->getColumnValue($row);
                /**
                 * @TODO Equal functional from HasOneSelect2
                 */
                $attribute = $this->attribute;
                $result = [];
                $pk = $this->getRelationObject()->getRelationPrimaryKey();
                foreach ($row->$attribute as $vsKeyword) {
                    $value = ArrayHelper::getValue($vsKeyword, $this->nameAttribute);

                    if (($url = $this->url) !== null) {
                        if (is_array($url)) {
                            $url = $url[0];
                        } else {
                            $url = str_replace('/index', '', $url);
                        }

                        $currentUrl = [$url . '/update', 'id' => $vsKeyword->$pk];
                        $value = $value . '&nbsp;' . Html::a('>>>', Url::to($currentUrl));
                    }

                    $result[] = $value;
                }

                return implode(', ', $result);
            };
        }

        $column = ArrayHelper::merge([
            'attribute' => $this->attribute,
            'format' => 'html',
            'value' => $valueClosure,
//                /**
//                 * @TODO Equal functional from HasOneSelect2
//                 */
//                $attribute = $this->attribute;
//                $result = [];
//                $pk = $this->getRelationObject()->getRelationPrimaryKey();
//                foreach ($row->$attribute as $vsKeyword) {
//                    $value = ArrayHelper::getValue($vsKeyword, $this->nameAttribute);
//
//                    if (($url = $this->url) !== null) {
//                        if (is_array($url)) {
//                            $url = $url[0];
//                        } else {
//                            $url = str_replace('/index', '', $url);
//                        }
//
//                        $currentUrl = [$url . '/update', 'id' => $vsKeyword->$pk];
//                        $value = $value . '&nbsp;' . Html::a('>>>', Url::to($currentUrl));
//                    }
//
//                    $result[] = $value;
//                }
//
//                return implode(', ', $result);

//            'value' => function ($row) {
//                $url = $this->url;
//                if (is_array($url)) {
//                    $url = $url[0];
//                } else {
//                    $url = str_replace('/index', '', $url);
//                }
//
//                $attribute = $this->attribute;
//
//                $url = [$url . '/update', 'id' => $row->$attribute];
//
//                $valueAttribute = $this->getRelationObject()->getColumnValue();
//                $value = ArrayHelper::getValue($row, $valueAttribute);
//
//                return Html::a($value, Url::to($url));
//            },
//                'value' => function () {
//                    return 'asdasd';
//                },
//                [
////                'language' => $this->getLanguage(),
//                'initValueText' => $sourceInitText,
//                'options' => [
//                    'multiple' => true,
//                ],
//                'pluginOptions' => [
//                    'allowClear' => true,
//                    'ajax' => [
//                        'cache' => true,
//                        'url' => Url::to($this->url),
//                        'dataType' => 'json',
//                        'data' => new JsExpression(<<<JS
//function (params) {
//  return {
//    "$nameParam": params.term,
//    page: params.page
//  };
//}
//JS
//                        )
//
//                    ],
//                ],
//            ],
        ], $column);

        if (!array_key_exists('filter', $column) || $column['filter'] !== false) {
            $column = ArrayHelper::merge([
                'filter' => '',//$sourceInitText,
                'filterType' => MultipleInput::class,
                'filterWidgetOptions' => ArrayHelper::merge($this->getMultipleInputWidgetOptions(), [
                    'max' => 1,
                    'min' => 1,
                    'addButtonPosition' => MultipleInput::POS_ROW,
                ]),
            ], $column);
        }

        return $column;
    }

//    public function getValue()
//    {
//        return false;
//    }

    /**
     * @param $column
     * @return array
     */
    protected function getMultipleInputWidgetOptions(): array
    {
        $nameParam = $this->getNameParam();
        $relation = $this->getRelationObject();
        if ($relation->isVia()) {
            $fromAttribute = $relation->getViaFromAttribute();
            $toAttribute = $relation->getViaToAttribute();
            $sourceInitText = $relation->getSourcesText();
            $viaRelationModelClass = $relation->getRelationModelClass();
            $viaRelationModel = new $viaRelationModelClass;
            $changeEvent = new JsExpression(<<<JS
    function () {
        var el = $(this),
            inputs = el.parent().parent().parent().find('input, select');
        if (el.val()) {
            inputs.not(el).attr('disabled', 'disabled');
        } else {
            inputs.not(el).attr('disabled', false);
        }
    }
JS
            );
            $targetFields = [
                'id' => [
                    'name' => 'id',
                    'type' => Select2::class,
                    'defaultValue' => null,
                    'value' => $sourceInitText,
                    'headerOptions' => [
                        'style' => 'width: 150px;',
                    ],
                    'options' => [
                        'initValueText' => $sourceInitText,
                        'pluginEvents' => [
                            'change' => $changeEvent,
                        ],
                        'pluginOptions' => [
                            'allowClear' => true,
                            'placeholder' => '',
                            'ajax' => [
                                'url' => Url::to($this->url),
                                'dataType' => 'json',
                                'data' => new JsExpression(<<<JS
    function(params) {
        return {
            "$nameParam": params.term
        };
    }
JS
                                )
                            ],
                        ],
                    ],
                ],
            ];
            $columns = ArrayHelper::merge($targetFields, $viaRelationModel->getMultipleInputFields(), $this->viaColumns);

            foreach ($columns as &$column) {
                if (empty($column['title']) && !empty($column['name'])) {
                    $column['title'] = Html::activeLabel($viaRelationModel, $column['name']);
                }
            }
        } else {
            $viaRelationModel = $this->getRelationObject()->getRelationModel(true);
            $pksFields = [];
            foreach ($viaRelationModel->primaryKey() as $primaryKey) {
                $pksFields[$primaryKey] = [
                    'type' => MultipleInputColumn::TYPE_HIDDEN_INPUT,
                    'name' => $primaryKey,
                ];
            }

            $multipleInputColumns = $viaRelationModel->getMultipleInputFields();
//            foreach ($multipleInputColumns as $multipleInputColumn) {
//                if (!empty($multipleInputColumn['type']) && $multipleInputColumn['type'] === Select2::class) {
//                    $sourceInitText = $this->getRelationSourceText($multipleInputColumn['name']);
//                    $multipleInputColumn['options']['initValueText'] = $sourceInitText;
//                }
//            }

            $columns = ArrayHelper::merge($pksFields, $multipleInputColumns, $this->viaColumns);
        }

        $widgetOptions = [
            'class' => MultipleInput::className(),
            'allowEmptyList' => true,
            'enableGuessTitle' => true,
            'model' => $viaRelationModel,
            'addButtonPosition' => MultipleInput::POS_HEADER,
            'columns' => $columns
        ];
        return $widgetOptions;
    }

    public function getMultipleInputField() {
        $options = $this->getMultipleInputWidgetOptions();

        return [
            'type' => MultipleInput::class,
            'name' => $this->attribute,
            'options' => $options,
        ];
    }

//    public function getRelationSourceText($attribute) {
//        $models = $this->getValue();
//        $result = ArrayHelper::map($models, $attribute, 'name');
//
//        return $result;
//    }
}