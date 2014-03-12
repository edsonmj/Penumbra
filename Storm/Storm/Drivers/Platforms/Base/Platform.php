<?php

namespace Storm\Drivers\Platforms\Base;

use \Storm\Drivers\Platforms;
use \Storm\Drivers\Base\Relational;

abstract class Platform extends Relational\Platform {
    private $DevelopmentMode;
    public function __construct(
            $DevelopmentMode = false,
            Relational\Expressions\Converters\IExpressionConverter $ExpressionConverter, 
            Relational\Columns\IColumnSet $ColumnSet, 
            Relational\PrimaryKeys\IKeyGeneratorSet $KeyGeneratorSet, 
            Relational\Queries\IExpressionCompiler $ExpressionCompiler, 
            Relational\Queries\ICriteriaCompiler $CriteriaCompiler, 
            Relational\Queries\ISelectCompiler $RequestCompiler, 
            Relational\Queries\IUpdateCompiler $ProcedureCompiler, 
            Relational\Queries\IIdentifierEscaper $IdentifierEscaper,
            Relational\Syncing\IDatabaseBuilder $DatabaseBuilder = null,
            Relational\Syncing\IDatabaseModifier $DatabaseModifier = null,
            Relational\Queries\IQueryExecutor $QueryExecutor) {
        $this->DevelopmentMode = $DevelopmentMode;
        
        parent::__construct(
                $ExpressionConverter, 
                $ColumnSet, 
                $KeyGeneratorSet, 
                $ExpressionCompiler,
                $RequestCompiler,
                $ProcedureCompiler,
                $CriteriaCompiler, 
                $IdentifierEscaper, 
                $DevelopmentMode ? 
                        new Platforms\Development\Syncing\DatabaseSyncer
                                ($DatabaseBuilder, $DatabaseModifier, true) : 
                        new Platforms\Production\Syncing\DatabaseSyncer(),
                $QueryExecutor);
    }
    
    protected function OnSetConnection(Relational\Queries\IConnection $Connection) {
        if($this->DevelopmentMode) {
            $IdentifiersAreCaseSensitive = $this->IdentifiersAreCaseSensitive($Connection);
            $this->GetDatabaseSyncer()->SetIdentifiersAreCaseSensitive($IdentifiersAreCaseSensitive);
        }
    }
    protected abstract function IdentifiersAreCaseSensitive(Relational\Queries\IConnection $Connection);
}

?>