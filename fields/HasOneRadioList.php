<?php
/**
 */

namespace execut\crudFields\fields;


use execut\crudFields\widgets\RadioListWithSubform;
use kartik\detail\DetailView;
use kartik\grid\GridView;
use kartik\select2\Select2;
use unclead\multipleinput\MultipleInputColumn;
use yii\base\Exception;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Inflector;
use yii\helpers\Url;
use yii\web\JsExpression;

class HasOneRadioList extends HasOneSelect2
{
    public $isRenderRelationFields = true;
    public $nameParam = null;
    public $createUrl = null;
    public $widgetOptions = [];
    public function getField() {
        $data = $this->getRelationObject()->getData(true);
        unset($data['']);

        if (empty($data)) {
            return false;
        }

        if ($this->getDisplayOnly()) {
            return parent::getField();
        }

        $data[''] = 'Новый автомобиль';

        return [
            'type' => DetailView::INPUT_WIDGET,
            'attribute' => $this->attribute,
            'widgetOptions' => [
                'class' => RadioListWithSubform::class,
                'clientOptions' => [
                    'relatedSelector' => 'tr:has(.related-' . $this->relation . ')',
                ],
                'data' => $data,
            ],
        ];
    }



    public function getFields($isWithRelationsFields = true) {
        $field = $this->getField();
        if ($field !== false) {
            $fields = [$this->attribute => $field];
        } else {
            $fields = [];
        }

        $fields = ArrayHelper::merge($fields, parent::getFields());

        return $fields;
    }
}