<?php

namespace PhalconApi\Data\Query\QueryParsers;

use PhalconApi\Data\Query;
use PhalconApi\Data\Query\Condition;
use PhalconApi\Data\Query\Sorter;

class UrlQueryParser
{
    const FIELDS = 'fields';
    const OFFSET = 'offset';
    const LIMIT = 'limit';
    const HAVING = 'having';
    const GROUPS = 'groups';
    const WHERE = 'where';
    const SORT = 'sort';
    const EXCLUDES = 'excludes';

    const OPERATOR_IS_EQUAL = 'e';
    const OPERATOR_IS_GREATER_THAN = 'gt';
    const OPERATOR_IS_GREATER_THAN_OR_EQUAL = 'gte';
    const OPERATOR_IS_LESS_THAN = 'lt';
    const OPERATOR_IS_LESS_THAN_OR_EQUAL = 'lte';
    const OPERATOR_IS_LIKE = 'l';
    const OPERATOR_IS_JSON_CONTAINS = 'jc';
    const OPERATOR_IS_NOT_EQUAL = 'ne';
    const OPERATOR_CONTAINS = 'c';
    const OPERATOR_NOT_CONTAINS = 'nc';

    const SORT_ASCENDING = 1;
    const SORT_DESCENDING = -1;

    protected $enabledFeatures = [ self::FIELDS, self::OFFSET, self::LIMIT, self::GROUPS, self::HAVING, self::WHERE, self::SORT, self::EXCLUDES ];


    public function createQuery($params)
    {
        $query = new Query;

        $fields = $this->isEnabled(self::FIELDS) ? $this->extractCommaSeparatedValues($params, 'fields') : null;
        $offset = $this->isEnabled(self::OFFSET) ? $this->extractInt($params, 'offset') : null;
        $limit = $this->isEnabled(self::LIMIT) ? $this->extractInt($params, 'limit') : null;
        $groups = $this->isEnabled(self::GROUPS) ? $this->extractCommaSeparatedValues($params, 'groups') : null;
        $having = $this->isEnabled(self::HAVING) ? $this->extractArray($params, 'having') : null;
        $where = $this->isEnabled(self::WHERE) ? $this->extractArray($params, 'where') : null;
        $or = $this->isEnabled(self::WHERE) ? $this->extractArray($params, 'or') : null;
        $in = $this->isEnabled(self::WHERE) ? $this->extractArray($params, 'in') : null;
        $sort = $this->isEnabled(self::SORT) ? $this->extractArray($params, 'sort') : null;
        $excludes = $this->isEnabled(self::EXCLUDES) ? $this->extractCommaSeparatedValues($params, 'exclude') : null;
        
        if ($fields) {
            $query->addManyFields($fields);
        }

        if ($offset) {
            $query->setOffset($offset);
        }

        if ($limit) {
            $query->setLimit($limit);
        }

        if ($excludes) {
            $query->addManyExcludes($excludes);
        }

        if ($groups) {
            $query->addManyGroups($groups);
        }

        if ($having) {

            foreach ($having as $field => $value) {

                $query->addCondition(new Condition(Condition::TYPE_AND, $field, Query::OPERATOR_IS_EQUAL, $value));
            }
        }

        if ($where) {

            foreach ($where as $field => $condition) {

                foreach ($condition as $rawOperator => $value) {

                    $operator = $this->extractOperator($rawOperator);

                    if (!is_null($operator)) {

                        $query->addCondition(new Condition(Condition::TYPE_AND, $field, $operator, $value));
                    }
                }
            }
        }

        if ($or) {

            foreach ($or as $where) {

                foreach ($where as $field => $conditions) {

                    foreach ($conditions as $rawOperator => $value) {

                        $operator = $this->extractOperator($rawOperator);

                        if (!is_null($operator)) {
                            $query->addCondition(new Condition(Condition::TYPE_OR, $field, $operator, $value));
                        }
                    }
                }
            }
        }

        if ($in) {

            foreach ($in as $field => $values) {

                if (!is_array($values)) {
                    continue;
                }

                $query->addCondition(new Condition(Condition::TYPE_AND, $field, Query::OPERATOR_IS_IN, $values));
            }
        }

        if ($sort) {

            foreach ($sort as $field => $rawDirection) {

                $direction = null;

                switch ($rawDirection) {

                    case self::SORT_DESCENDING:
                        $direction = Sorter::DESCENDING;
                        break;
                    case self::SORT_ASCENDING:
                    default:
                        $direction = Sorter::ASCENDING;
                        break;
                }

                $query->addSorter(new Sorter($field, $direction));
            }
        }

        return $query;
    }

    /**
     * @param $feature
     *
     * @return static
     */
    public function enable($feature)
    {
        if(!in_array($feature, $this->enabledFeatures)){
            $this->enabledFeatures[] = $feature;
        }

        return $this;
    }

    /**
     * @param $feature
     *
     * @return static
     */
    public function disable($feature)
    {
        $index = array_search($feature, $this->enabledFeatures);

        if($index !== false){
            unset($this->enabledFeatures[$index]);
        }

        return $this;
    }

    private function isEnabled($feature)
    {
        return in_array($feature, $this->enabledFeatures);
    }


    private function getValue($data, $field)
    {
        return array_key_exists($field, $data) ? $data[$field] : null;
    }

    private function extractCommaSeparatedValues($data, $field)
    {
        if (!$fields = $this->getValue($data, $field)) {
            return null;
        }

        return explode(',', $fields);
    }

    private function extractInt($data, $field)
    {
        if (!$int = $this->getValue($data, $field)) {
            return null;
        }

        return (int)$int;
    }

    private function extractArray($data, $field)
    {
        if (!$result = $this->getValue($data, $field)) {
            return null;
        }

        if (!$result = json_decode($result, true)) {
            return null;
        }

        return $result;
    }

    private function operatorMap()
    {
        return [
            self::OPERATOR_IS_EQUAL => Query::OPERATOR_IS_EQUAL,
            self::OPERATOR_IS_GREATER_THAN => Query::OPERATOR_IS_GREATER_THAN,
            self::OPERATOR_IS_GREATER_THAN_OR_EQUAL => Query::OPERATOR_IS_GREATER_THAN_OR_EQUAL,
            self::OPERATOR_IS_LESS_THAN => Query::OPERATOR_IS_LESS_THAN,
            self::OPERATOR_IS_LESS_THAN_OR_EQUAL => Query::OPERATOR_IS_LESS_THAN_OR_EQUAL,
            self::OPERATOR_IS_LIKE => Query::OPERATOR_IS_LIKE,
            self::OPERATOR_IS_JSON_CONTAINS => Query::OPERATOR_IS_JSON_CONTAINS,
            self::OPERATOR_IS_NOT_EQUAL => Query::OPERATOR_IS_NOT_EQUAL,
            self::OPERATOR_CONTAINS => Query::OPERATOR_CONTAINS,
            self::OPERATOR_NOT_CONTAINS => Query::OPERATOR_NOT_CONTAINS
        ];
    }

    private function extractOperator($operator)
    {
        $operatorMap = $this->operatorMap();

        if (array_key_exists($operator, $operatorMap)) {
            return $operatorMap[$operator];
        }

        return null;
    }
}