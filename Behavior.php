<?php
/**
 */

namespace execut\crudFields;


use execut\crudFields\fields\Field;
use yii\base\Behavior as BaseBehavior;
use yii\data\ActiveDataProvider;

class Behavior extends BaseBehavior
{
    protected $_plugins = [];
    public function setPlugins($plugins) {
        $this->_plugins = $plugins;
    }

    protected $_pluginsIsInited = false;
    protected function initPlugins() {
        if (!$this->_pluginsIsInited) {
            foreach ($this->_plugins as $key => $plugin) {
                if (is_array($plugin)) {
                    $this->_plugins[$key] = \yii::createObject($plugin);
                }
            }

            $this->_pluginsIsInited = true;
        }
    }

    public function getPlugins() {
        $this->initPlugins();
        return $this->_plugins;
    }

    public function getPluginsFields() {
        $result = [];
        foreach ($this->plugins as $plugin) {
            $result = array_merge($result, $plugin->getFields());
        }

        return $result;
    }

    protected $_fields = [];
    public function setFields($fields) {
        $this->_fields = $fields;
        return $this;
    }

    public function getFields() {
        $fields = $this->_fields;

        $fields = array_merge($fields, $this->getPluginsFields());
        foreach ($fields as $key => $field) {
            if (is_string($field)) {
                if (class_exists($field)) {
                    $field = ['class' => $field];
                } else {
                    $field = ['attribute' => $field];
                }
            }

            if (is_array($field)) {
                if (empty($field['class'])) {
                    $class = Field::class;
                } else {
                    $class = $field['class'];
                    unset($field['class']);
                }

                $field = new $class($field);
            }

            $field->model = $this->owner;

            $fields[$key] = $field;
        }

        return $fields;
    }

    public function getGridColumns() {
        $columns = [];
        foreach ($this->getFields() as $field) {
            $column = $field->getColumn();
            if ($column !== false) {
                $columns[] = $column;
            }
        }

        return $columns;
    }

    public function getFormFields() {
        $columns = [];
        foreach ($this->getFields() as $field) {
            $field = $field->getField();
            if ($field !== false) {
                $columns[] = $field;
            }
        }

        return $columns;
    }

    public function applyScopes($query) {
        foreach ($this->getFields() as $field) {
            $query = $field->applyScopes($query);
        }

        return $query;
    }

    public function search() {
        $modelClass = $this->owner->className();
        $q = $modelClass::find();
        $q = $this->applyScopes($q);
        $dataProvider = new ActiveDataProvider([
            'query' => $q,
        ]);

        return $dataProvider;
    }

    public function rules() {
        $rules = [];
        foreach ($this->getFields() as $field) {
            $fieldRules = $field->rules();
            if ($fieldRules !== false) {
                $rules = array_merge($rules, $fieldRules);
            }
        }

        return $rules;
    }
}