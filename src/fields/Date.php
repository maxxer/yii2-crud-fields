<?php
/**
 * @author Mamaev Yuriy (eXeCUT)
 * @link https://github.com/execut
 * @copyright Copyright (c) 2020 Mamaev Yuriy (eXeCUT)
 * @license http://www.apache.org/licenses/LICENSE-2.0
 */
namespace execut\crudFields\fields;

use kartik\daterange\DateRangePicker;
use kartik\detail\DetailView;
use yii\base\Exception;
use yii\db\ActiveQueryInterface;
use yii\helpers\ArrayHelper;

/**
 * Date and time field
 * @package execut\crudFields
 */
class Date extends Field
{
    /**
     * @var bool Is has time part
     */
    public $isTime = false;
    /**
     * @var bool Is a show field when empty
     */
    public $showIfEmpty = false;
    /**
     * @var bool Is has microseconds part
     */
    public $isWithMicroseconds = false;

    /**
     * {@inheritdoc}
     */
    protected function getRules(): array
    {
        $self = $this;
        return ArrayHelper::merge(parent::getRules(), [
            $this->attribute . 'Date' => [
                $this->attribute,
                function () use ($self) {
                    if (!$self->extractIntervalDates()) {
                        $self->model->addError($self->attribute, 'Bad date interval "' . $self->getValue() . '"');
                        return false;
                    }

                    return true;
                },
                'on' => self::SCENARIO_GRID,
            ],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getColumn()
    {
        $parentColumn = parent::getColumn();
        if ($parentColumn === false) {
            return false;
        }

        $widgetOptions = $this->getWidgetOptions();

        $column = [
            'format' => function ($value) {
                return $this->formatDateValue($value);
            },
        ];

        if ($this->rules !== false) {
            if (empty($this->getValue()) || $this->extractIntervalDates()) {
                $column['filter'] = DateRangePicker::widget($widgetOptions);
            } else {
                $column['filter'] = true;
            }
        }

        return ArrayHelper::merge($column, $parentColumn);
    }

    /**
     * {@inheritdoc}
     */
    public function getField()
    {
        if (empty($this->getValue()) && $this->getDisplayOnly()) {
            if ($this->showIfEmpty) {
                return [
                    'label' => $this->getLabel(),
                    'displayOnly' => true,
                    'value' => '-',
                ];
            } else {
                return false;
            }
        }

        $field = parent::getField();
        if ($field === false) {
            return false;
        }

        if ($this->getDisplayOnly()) {
            return array_merge($field, [
                'displayOnly' => true,
                'value' => function () {
                    $value = $this->getValue();
                    if (!empty($value)) {
                        return $this->formatDateValue($value);
                    }
                },
            ]);
        }

        if ($this->isTime) {
            $type = DetailView::INPUT_DATETIME;
        } else {
            $type = DetailView::INPUT_DATE;
        }

        return [
            'type' => $type,
            'attribute' => $this->attribute,
            'format' => ['date', $this->getFormat()],
            'widgetOptions' => $this->getWidgetOptions(),
        ];
    }

    /**
     * Convert date to needed format
     * @param string $value
     * @return string
     * @throws Exception
     */
    protected function formatDateValue($value)
    {
        if (!empty($value)) {
            $dateTime = \DateTime::createFromFormat($this->getDatabaseFormat(true), $value, new \DateTimeZone(\yii::$app->formatter->defaultTimeZone));
            if (!$dateTime) {
                $dateTime = \DateTime::createFromFormat($this->getDatabaseFormat(false), $value, new \DateTimeZone(\yii::$app->formatter->defaultTimeZone));
            }
            if (!$dateTime) {
                throw new Exception('Failed to format date ' . $value . ' to format ' . $this->getDatabaseFormat(false));
            }

            return $dateTime->format($this->getFormat());
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    protected function initDetailViewField(DetailViewField $field)
    {
        $field->setDisplayOnly(true);
    }

    /**
     * {@inheritdoc}
     */
    public function getMultipleInputField()
    {
        if ($this->getDisplayOnly()) {
            return false;
        }

        return parent::getMultipleInputField(); // TODO: Change the autogenerated stub
    }

    /**
     * {@inheritdoc}
     */
    protected function applyFieldScopes(ActiveQueryInterface $query)
    {
        $modelClass = $query->modelClass;
        $attribute = $this->attribute;
        $t = $modelClass::tableName();
        $value = $this->getValue();
        if (!empty($value) && strpos($value, ' - ') !== false) {
            list($from, $to) = $this->extractIntervalDates();

            $query->andFilterWhere(['>=', $t . '.' . $attribute, $from])
                ->andFilterWhere(['<=', $t . '.' . $attribute, $to]);
        }
    }

    /**
     * Extract interval of dates from field value
     * @return array|false
     * @throws Exception
     */
    protected function extractIntervalDates()
    {
        $value = $this->getValue();
        if (strpos($value, ' - ') === false) {
            return false;
        }
        list($from, $to) = explode(' - ', $value);

        if (!$from || !$to) {
            return false;
        }

        if (!$this->isTime) {
            $from = $from . ' 00:00:00';
            $to = $to . ' 23:59:59';
        };

        $dateTimeFormat = $this->getFormat(false, false);
        $from = self::convertToUtc($from, $dateTimeFormat);
        if (!$from) {
            return false;
        }
        $dateTimeFormat = $this->getFormat(false, false);
        $to = self::convertToUtc($to, $dateTimeFormat);
        if (!$to) {
            return false;
        }

        return [$from, $to];
    }

    /**
     * Convert date to UTC format
     * @param string $dateTimeStr Datetime string
     * @param string $format Datetime format
     * @return bool|string
     */
    public static function convertToUtc($dateTimeStr, $format)
    {
        $dateTime = \DateTime::createFromFormat(
            $format,
            $dateTimeStr,
            self::getApplicationTimeZone()
        );

        if (!$dateTime) {
            return false;
        }

        $dateTime->setTimezone(new \DateTimeZone(self::getDatabaseTimeZone()));

        return $dateTime->format('Y-m-d H:i:s.u');
    }

    /**
     * Get database timezone
     * @return string
     */
    protected static function getDatabaseTimeZone()
    {
        return 'Europe/Moscow';
    }

    /**
     * Get application timezone
     * @return \DateTimeZone
     */
    private static function getApplicationTimeZone()
    {
        return (new \DateTimeZone(\Yii::$app->timeZone));
    }

    /**
     * Get widget options
     * @return array
     */
    protected function getWidgetOptions(): array
    {
        $format = $this->getFormat(false, false);
        $pluginOptions = [
            'format' => $this->getFormat(true),
            'locale' => ['format' => $format, 'separator' => ' - '],
            'todayHightlight' => true,
            'showSeconds' => true,
            'minuteStep' => 1,
            'secondStep' => 1,
        ];

        if ($this->isTime) {
            $pluginOptions = ArrayHelper::merge($pluginOptions, [
                'timePicker' => true,
                'timePickerIncrement' => 15,
            ]);
        }

        $widgetOptions = [
            'attribute' => $this->attribute,
            'model' => $this->model,
            'convertFormat' => true,
            'pluginOptions' => $pluginOptions,
        ];

        return $widgetOptions;
    }

    /**
     * Get current humanize format of datetime
     * @param bool $isForJs Is for javascript
     * @param bool|null $isWithMicroseconds Is has microseconds part
     * @return string
     */
    protected function getFormat($isForJs = false, $isWithMicroseconds = null): string
    {
        if ($isForJs) {
            $format = 'yyyy-MM-dd';
        } else {
            $format = 'd.m.Y';
        }

        if ($this->isTime) {
            if ($isForJs) {
                $isWithMicroseconds = false;
            }

            $format .= ' ' . $this->getTimeFormat($isWithMicroseconds);
        }

        return $format;
    }

    /**
     * Get database date format string
     * @param bool|null $isWithMicroseconds Is has microseconds part
     * @return string
     */
    protected function getDatabaseFormat($isWithMicroseconds = null)
    {
        $format = 'Y-m-d';
        if ($this->isTime) {
            $format .= ' ' . $this->getTimeFormat($isWithMicroseconds);
        }

        return $format;
    }

    /**
     * Get database time format string
     * @param bool|null $isWithMicroseconds Is has microseconds part
     * @return string
     */
    protected function getTimeFormat($isWithMicroseconds = null): string
    {
        if (!$this->isTime) {
            return false;
        }

        $format = 'H:i:s';
        if ($isWithMicroseconds || $isWithMicroseconds === null && $this->isWithMicroseconds) {
            $format .= '.u';
        }

        return $format;
    }
}
