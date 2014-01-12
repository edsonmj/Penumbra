<?php

namespace Storm\Drivers\Platforms\Mysql;

use \Storm\Core\Relational;
use \Storm\Drivers\Base\Relational\Expressions\Expression;
use \Storm\Core\Relational\Expressions\Expression as CoreExpression;
use \Storm\Drivers\Base\Relational\Expressions as E;
use \Storm\Core\Relational\Expressions as EE;
use \Storm\Drivers\Base\Relational\Expressions\Operators as O;

final class ObjectMapper extends E\ObjectMapper {
    protected function ObjectDataTypes() {
        return [
            new Columns\DataTypes\DateTimeDataType(),
        ];
    }
    // <editor-fold defaultstate="collapsed" desc="Date and Time">
    
    
    public function DateInterval(\DateInterval $Value) {
        return $Value;
    }
    
    public function DateInterval___construct(array $ArgumentExpressions) {
        if(!($ArgumentExpressions[0] instanceof EE\ConstantExpression)) {
            throw new \Exception();
        }
        
        return new \DateInterval($ArgumentExpressions[0]->GetValue());
    }

    // </editor-fold>
}

?>