<?php

namespace Storm\Drivers\Platforms\Base\Queries;

use \Storm\Core\Relational;
use \Storm\Drivers\Base\Relational\Queries\QueryBuilder;

abstract class CriteriaCompiler implements ICriteriaCompiler {

    final public function AppendWhere(QueryBuilder $QueryBuilder, array $Expressions) {
        if(count($Expressions) > 0) {
            $this->AppendWhereClause($QueryBuilder, $Expressions);
        }
    }

    final public function AppendOrderBy(QueryBuilder $QueryBuilder, array $Expressions) {
        if(count($Expressions) > 0) {
            $this->AppendOrderByClause($QueryBuilder, $Expressions);
        }
    }
    
    final public function AppendRange(QueryBuilder $QueryBuilder, $RangeStart, $RangeAmount) {
        if($RangeStart !== 0 && $RangeAmount !== null) {
            $this->AppendGroupByClause($QueryBuilder, $RangeStart, $RangeAmount);
        }
    }

    protected abstract function AppendTableDefinitionClause(QueryBuilder $QueryBuilder, Relational\ITable $Table, array $Joins = null);
    
    protected abstract function AppendWhereClause(QueryBuilder $QueryBuilder, array $PredicateExpressions);
    
    protected abstract function AppendOrderByClause(QueryBuilder $QueryBuilder, \SplObjectStorage $ExpressionAscendingMap);
    
    protected abstract function AppendRangeClause(QueryBuilder $QueryBuilder, $Offset, $Limit);
}

?>