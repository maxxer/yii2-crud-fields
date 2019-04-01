<?php
/**
 */

namespace execut\crudFields\fields;


use kartik\detail\DetailView;
use kartik\grid\GridView;
use execut\crudFields\widgets\Select2;
use unclead\multipleinput\MultipleInputColumn;
use yii\base\Exception;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Inflector;
use yii\helpers\UnsetArrayValue;
use yii\helpers\Url;
use yii\web\JsExpression;

class HasOneSelect2 extends Field
{
    public $nameParam = null;
    public $createUrl = null;
    public $widgetOptions = [];
    public $existsValidatorOptions = [];
    public $isRedefineWidgetOptions = false;

    public function getField() {
        $field = parent::getField();
        if ($field === false) {
            return $field;
        }

        $widgetOptions = $this->getSelect2WidgetOptions();
        $rowOptions = [];
        if ($this->getDisplayOnly() && empty($this->getValue())) {
            $type = DetailView::INPUT_HIDDEN;
            $rowOptions['style'] = 'display:none';
        } else {
            $type = DetailView::INPUT_WIDGET;
            if ($this->createUrl) {
                $widgetOptions['addon'] = [
                    'append' => [
                        'content' => $this->getCreateButton(),
                        'asButton' => true
                    ]
                ];
            }
//            $widgetOptions['data'][''] = '';
        }

//        if ($this->isRenderRelationFields) {
//            $relationName = $this->getRelationObject()->getName();
//            $widgetOptions['pluginEvents'] = [
//                'change' => new JsExpression(<<<JS
//        function () {
//            var el = $(this),
//                inputs = $('.related-$relationName input').not(el),
//                parents = inputs.not(el).attr('disabled', 'disabled').parent().parent().parent().parent().parent();
//            if (el.val()) {
//                inputs.attr('disabled', 'disabled');
//                parents.hide();
//            } else {
//                inputs.attr('disabled', false).val('');
//                parents.show();
//            }
//        }
//JS
//                )
//            ];
//        }

//        $sourceInitText = $this->getRelationObject()->getSourceText();
//        if (empty($field['type'])) {
//        if (!empty($field['widgetOptions'])) {
//            $widgetOptions = $field['widgetOptions'];
//        }
        if ($this->isRedefineWidgetOptions) {
            $widgetOptions = [];
        }

        $field = ArrayHelper::merge([
            'type' => $type,
//            'widgetClass' => Select2::class,
            'value' => $this->getRelationObject()->getColumnValue($this->model),
            'format' => 'raw',
            'widgetOptions' => $widgetOptions,
            'fieldConfig' => [
                //                'template' => "{input}$createButton\n{error}\n{hint}",
            ],
            'displayOnly' => $this->getIsRenderRelationFields(),
            'rowOptions' => $rowOptions,
        ], $field);

        return $field;
    }

    protected function getRules(): array
    {
        $rules = parent::getRules(); // TODO: Change the autogenerated stub

        $rules[$this->attribute . '_limit'] = [$this->attribute, 'filter', 'filter' => function ($v) {
            if (is_string($v)) {
                $column = $this->model->getTableSchema()->getColumn($this->attribute);
                if ($column) {
                    return $column->phpTypecast($v);
                }
            } else if (is_array($v)) {
                $v = array_filter($v);
            }

            return $v;
        }];
//        $rules[$this->attribute . '_in'] = ArrayHelper::merge([
//            $this->attribute,
//            'exist',
//            'targetClass' => $this->getRelationObject()->getRelationModelClass(),
//            'targetAttribute' => $this->getRelationObject()->getRelationPrimaryKey(),
//            'on' => [
//                self::SCENARIO_FORM,
//            ],
//        ], $this->existsValidatorOptions);

        return $rules;
    }

//    public function getFields() {
//        $relationModelClass = $this->getRelationObject()->getRelationModelClass();
//        $relationModel = new $relationModelClass;
//
//        return $relationModel->getBehavior('fields')->getFields();
//    }

    public function getNameParam() {
        if ($this->nameParam !== null) {
            return $this->nameParam;
        }

        $formName = $this->getRelationObject()->getRelationFormName();

        return $formName . '[' . $this->nameAttribute . ']';
    }


    public function getColumn() {
        $column = parent::getColumn();
        if ($column === false) {
            return false;
        }

        $sourceInitText = $this->getSourcesText();

//        $sourcesNameAttribute = $modelClass::getFormAttributeName('name');
        if (empty($this->attribute)) {
            throw new Exception('Attribute is required');
        }

        $filterWidgetOptions = [];
        if (!array_key_exists('filter', $column)) {
            $filterWidgetOptions = ArrayHelper::merge($this->getSelect2WidgetOptions(), [
                'options' => [
                    'multiple' => true
                ],
            ]);
        }
        $filterWidgetOptions['isRenderLink'] = false;

        //        var_dump($sourceInitText);
//        var_dump($filterWidgetOptions);
//        exit;
        $column = ArrayHelper::merge([
            'attribute' => $this->attribute,
            'format' => 'raw',
//            'value' => $this->getData(),
            'value' => function ($row) {
                $value = $this->getRelationObject()->getColumnValue($row);

                return $value;
            },
//                'value' => function () {
//                    return 'asdasd';
//                },
            'filter' => $sourceInitText,
            'filterType' => Select2::class,
            'filterWidgetOptions' => $filterWidgetOptions,
        ], $column);

        return $column;
    }

    public function getLanguage() {
        return substr(\yii::$app->language, 0, 2);
    }

    public function getMultipleInputField()
    {
        return [
            'type' => Select2::class,
            'name' => $this->attribute,
            'options' => $this->getSelect2WidgetOptions(),
        ];
    }

    /**
     * @return array
     */
    protected function getSelect2WidgetOptions(): array
    {
        $sourceInitText = $this->getSourcesText();
        if (empty($sourceInitText)) {
            $sourceInitText = null;
        }

        $nameParam = $this->getNameParam();
        $widgetOptions = [
            'class' => Select2::class,
            'theme' => Select2::THEME_BOOTSTRAP,
            'language' => $this->getLanguage(),
            'initValueText' => $sourceInitText,
            'pluginOptions' => [
                'allowClear' => true,
            ],
            'options' => [
                'placeholder' => $this->getLabel(),
            ],
            'isRenderLink' => !$this->isNoRenderRelationLink,
        ];

        if ($this->url !== null) {
            $widgetOptions = ArrayHelper::merge($widgetOptions, [
                'showToggleAll' => false,
                'url' => $this->url,
                'pluginOptions' => [
                    'ajax' => [
                        'dataType' => 'json',
                        'data' => new JsExpression(<<<JS
            function(params) {
                return {
                    "$nameParam": params.term,
                    page: params.page
                };
            }
JS
                        )
                    ]
                ],
            ]);
        } else {
            $data = $this->getData();
            $widgetOptions = ArrayHelper::merge($widgetOptions, [
                'data' => $data,
            ]);
        }

        $widgetOptions = ArrayHelper::merge($widgetOptions, $this->widgetOptions);

        return $widgetOptions;
    }

    /**
     * @return string
     */
    protected function getCreateButton(): string
    {
        return Html::a('Создать', $this->createUrl, [
            'class' => 'btn btn-primary',
            'title' => 'Создать новый автомобиль',
            'data-toggle' => 'tooltip',
            'target' => '_blank',
        ]);
    }

    /**
     * @return array
     */
    protected function getSourcesText(): array
    {
        if (($relation = $this->getRelationObject())) {
            $sourcesText = $relation->getSourcesText();
            if (empty($sourcesText) && ($value = $this->getValue())) {
                $sourcesText = [$value => $value];
            }

            return $sourcesText;
        }

        $result = $this->getData();

        return $result;
    }
}