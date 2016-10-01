<?php
namespace Friday\Validator;

use Friday;
use Friday\Base\Awaitable;
use Friday\Base\Deferred;
use Friday\Base\Exception\RuntimeException;
use Friday\Db\ActiveRecordInterface;
use Friday\Helper\AwaitableHelper;
use Throwable;

/**
 * UniqueValidator validates that the attribute value is unique in the specified database table.
 *
 * UniqueValidator checks if the value being validated is unique in the table column specified by
 * the ActiveRecord class [[targetClass]] and the attribute [[targetAttribute]].
 *
 * The following are examples of validation rules using this validator:
 *
 * ```php
 * // a1 needs to be unique
 * ['a1', 'unique']
 * // a1 needs to be unique, but column a2 will be used to check the uniqueness of the a1 value
 * ['a1', 'unique', 'targetAttribute' => 'a2']
 * // a1 and a2 need to be unique together, and they both will receive error message
 * [['a1', 'a2'], 'unique', 'targetAttribute' => ['a1', 'a2']]
 * // a1 and a2 need to be unique together, only a1 will receive error message
 * ['a1', 'unique', 'targetAttribute' => ['a1', 'a2']]
 * // a1 needs to be unique by checking the uniqueness of both a2 and a3 (using a1 value)
 * ['a1', 'unique', 'targetAttribute' => ['a2', 'a1' => 'a3']]
 * ```
 */
class UniqueValidator extends Validator
{
    /**
     * @var string the name of the ActiveRecord class that should be used to validate the uniqueness
     * of the current attribute value. If not set, it will use the ActiveRecord class of the attribute being validated.
     * @see targetAttribute
     */
    public $targetClass;
    /**
     * @var string|array the name of the ActiveRecord attribute that should be used to
     * validate the uniqueness of the current attribute value. If not set, it will use the name
     * of the attribute currently being validated. You may use an array to validate the uniqueness
     * of multiple columns at the same time. The array values are the attributes that will be
     * used to validate the uniqueness, while the array keys are the attributes whose values are to be validated.
     * If the key and the value are the same, you can just specify the value.
     */
    public $targetAttribute;
    /**
     * @var string|array|\Closure additional filter to be applied to the DB query used to check the uniqueness of the attribute value.
     * This can be a string or an array representing the additional query condition (refer to [[\yii\db\Query::where()]]
     * on the format of query condition), or an anonymous function with the signature `function ($query)`, where `$query`
     * is the [[\yii\db\Query|Query]] object that you can modify in the function.
     */
    public $filter;


    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        if ($this->message === null) {
            $this->message = Friday::t('{attribute} "{value}" has already been taken.');
        }
    }

    /**
     * @inheritdoc
     */
    public function validateAttribute($model, $attribute) : Awaitable
    {

        /* @var $targetClass ActiveRecordInterface */
        $targetClass = $this->targetClass === null ? get_class($model) : $this->targetClass;
        $targetAttribute = $this->targetAttribute === null ? $attribute : $this->targetAttribute;

        if (is_array($targetAttribute)) {
            $params = [];
            foreach ($targetAttribute as $k => $v) {
                $params[$v] = is_int($k) ? $model->$v : $model->$k;
            }
        } else {
            $params = [$targetAttribute => $model->$attribute];
        }

        foreach ($params as $value) {
            if (is_array($value)) {
                $this->addError($model, $attribute, Friday::t('{attribute} is invalid.'));

                return AwaitableHelper::result();
            }
        }

        $query = $targetClass::find();
        $query->andWhere($params);

        if ($this->filter instanceof \Closure) {
            call_user_func($this->filter, $query);
        } elseif ($this->filter !== null) {
            $query->andWhere($this->filter);
        }
        /**
         * @var Friday\Base\BaseObject $targetClass
         */
        $deferred = new Deferred();
        if (!$model instanceof ActiveRecordInterface || $model->getIsNewRecord() || $model->className() !== $targetClass::className()) {
            // if current $model isn't in the database yet then it's OK just to call exists()
            // also there's no need to run check based on primary keys, when $targetClass is not the same as $model's class
            $query->exists()->await(function ($exists) use($deferred, $model, &$attribute) {
                if($exists instanceof Throwable){
                    $deferred->exception($exists);
                } else {
                    if ($exists) {
                        $this->addError($model, $attribute, $this->message);
                    }
                    $deferred->result();
                }
            });
        } else {
            /**
             * @var ActiveRecordInterface $targetClass
             */
            // if current $model is in the database already we can't use exists()
            /* @var $models ActiveRecordInterface[] */
            $query->limit(2)->all()->await(function ($models) use ($deferred, &$params, $targetClass, $model, &$attribute){
                if($models instanceof Throwable){
                    $deferred->exception($models);
                } elseif(is_array($models)) {
                    $n = count($models);
                    if ($n === 1) {
                        $targetClass::primaryKey()->await(function ($primaryKeys) use ($deferred, $model, $models, &$attribute, &$params){
                            if($primaryKeys instanceof Throwable){
                                $deferred->exception($primaryKeys);
                            } elseif(is_array($primaryKeys)) {
                                $keys = array_keys($params);
                                sort($keys);
                                sort($primaryKeys);
                                if ($keys === $primaryKeys) {
                                    // primary key is modified and not unique
                                    if ($model->getOldPrimaryKey() != $model->getPrimaryKey()) {
                                        $this->addError($model, $attribute, $this->message);
                                    }
                                } else {
                                    // non-primary key, need to exclude the current record based on PK
                                    if ($models[0]->getPrimaryKey() != $model->getOldPrimaryKey()) {
                                        $this->addError($model, $attribute, $this->message);
                                    }
                                }
                                $deferred->result();
                            } else {
                                $deferred->exception(new RuntimeException("Unexpected result type."));
                            }

                        });
                    } elseif($n > 1) {
                        $this->addError($model, $attribute, $this->message);
                        $deferred->result();
                    }
                } else {
                    $deferred->exception(new RuntimeException("Unexpected result type."));
                }
            });

        }

        return $deferred->awaitable();
    }
}
