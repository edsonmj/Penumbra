<?php

namespace Storm\Core\Mapping;

use \Storm\Core\Object;
use \Storm\Core\Relational;
use \Storm\Core\Containers\Registrar;
use \Storm\Core\Containers\Map;

abstract class ObjectRelationalMapper extends ObjectRelationalMapperBase {
    /**
     * @var RequestMapper
     */
    private $RequestMapper;
    /**
     * @var ProcedureMapper
     */
    private $ProcedureMapper;
    /**
     * @var UnitOfWorkTransactionMapper
     */
    private $UnitOfWorkTransactionMapper;
    
    public function __construct() {
        $this->Domain = $this->Domain();
        $this->ProcedureMapper = $this->Database();
        
        $Registrar = new Registrar(IEntityRelationalMap::IEntityRelationalMapType);
        $this->RegisterEntityRelationMaps($Registrar);
        foreach($Registrar->GetRegistered() as $EntityRelationalMap) {
            $this->AddEntityRelationMap($EntityRelationalMap);
        }
    }
    
    protected abstract function Domain();
    protected abstract function Database();
    protected abstract function RegisterEntityRelationMaps(Registrar $Registrar);
    
    /**
     * @return Object\Domain
     */
    final public function GetDomain() {
        return $this->Domain;
    }
    
    /**
     * @return Relational\Database
     */
    final public function GetDatabase() {
        return $this->ProcedureMapper;
    }
        
    final protected function AddEntityRelationMap(IEntityRelationalMap $EntityRelationalMap) {
        $EntityRelationalMap->Initialize($this);
        
        $EntityType = $EntityRelationalMap->GetEntityType();
        if(!$this->Domain->HasEntityMap($EntityType))
            throw new \InvalidArgumentException('$EntityRelationMap must have an EntityMap of this Domain');
        
        $this->UnitOfWorkTransactionMapper[$EntityType] = $EntityRelationalMap;
        $this->EntityRelationMapsByPrimaryKeyTable[$EntityRelationalMap->GetPrimaryKeyTable()->GetName()] = $EntityRelationalMap;
    }
    
    final public function HasRelationMap($EntityType) {
        return isset($this->UnitOfWorkTransactionMapper[$EntityType]);
    }
    
    /**
     * @return IEntityRelationalMap
     */
    final public function GetRelationMap($EntityType) {
        if($this->HasRelationMap($EntityType))
            return $this->UnitOfWorkTransactionMapper[$EntityType];
        else {
            $ParentType = get_parent_class($EntityType);
            if($ParentType === false)
                return null;
            else
                return $this->GetRelationMap($ParentType);
        }
    }
    
    /**
     * @return IEntityRelationalMap
     */
    final public function GetRelationMapByPrimaryKeyTable($TableName) {
        return isset($this->EntityRelationMapsByPrimaryKeyTable[$TableName]) ?
                $this->EntityRelationMapsByPrimaryKeyTable[$TableName] : null;
    }
    
    /**
     * @return IEntityRelationalMap 
     */
    final protected function VerifyRelationalMap($EntityType) {
        $RelationMap = $this->GetRelationMap($EntityType);
        if($RelationMap === null)
            throw new \Storm\Core\Exceptions\UnmappedEntityException($EntityType);
        
        return $RelationMap;
    }
    
    final public function Load(Object\IRequest $ObjectRequest) {
        $EntityType = $ObjectRequest->GetEntityType();
        
        $RelationalRequest = $this->MapRequest($ObjectRequest);
        $Rows = $this->ProcedureMapper->Load($RelationalRequest);
        
        $RevivedEntities = $this->ReviveEntities($EntityType, $Rows);
        
        if($ObjectRequest->IsSingleEntity()) {
            return count($RevivedEntities) > 0 ? reset($RevivedEntities) : null;
        }
        else {
            return $RevivedEntities;
        }
    }
    
    final public function Commit(
            array $EntitiesToPersist,
            array $ProceduresToExecute,
            array $EntitiesToDiscard,
            array $CriteriaToDiscard) {
        
        $UnitOfWork = $this->Domain->BuildUnitOfWork(
                $EntitiesToPersist, 
                $ProceduresToExecute, 
                $EntitiesToDiscard, 
                $CriteriaToDiscard);
        
        $Transaction = new Relational\Transaction();
        $this->MapUnitOfWorkToTransaction($UnitOfWork, $Transaction);
        
        $this->ProcedureMapper->Commit($Transaction);
    }
    
    // <editor-fold defaultstate="collapsed" desc="Request and Operation mappers">
    
    /**
     * @param Object\IRequest $ObjectProcedure
     * @return Relational\Procedure
     */
    final public function MapProcedure(Object\IProcedure $ObjectProcedure) {
        $EntityRelationalMap = $this->VerifyRelationalMap($ObjectProcedure->GetEntityType());
        
        $RelationalProcedure = new Relational\Procedure(
                $EntityRelationalMap->GetMappedPersistTables(), $EntityRelationalMap->GetCriterion());
 
        $this->MapCriterion($EntityRelationalMap, $ObjectProcedure->GetCriterion(), $RelationalProcedure->GetCriterion());
        foreach($this->MapExpressions($EntityRelationalMap, $ObjectProcedure->GetExpressions()) as $MappedExpression) {
            $RelationalProcedure->AddExpression($MappedExpression);
        }
        
        return $RelationalProcedure;
    }
    
    /**
     * @param Object\IRequest $ObjectRequest
     * @return Relational\Request
     */
    final public function MapRequest(Object\IRequest $ObjectRequest) {
        $EntityRelationalMap = $this->VerifyRelationalMap($ObjectRequest->GetEntityType());
        
        $RelationalRequest = new Relational\Request(array(), $EntityRelationalMap->GetCriterion());
        $this->MapPropetiesToRelationalRequest($EntityRelationalMap, $RelationalRequest, $ObjectRequest->GetProperties());
        
        $this->MapCriterion($EntityRelationalMap, $ObjectRequest->GetCriterion(), $RelationalRequest->GetCriterion());
        
        return $RelationalRequest;
    }
    
    /**
     * @internal
     */
    final public function MapEntityToRelationalRequest($EntityType, Relational\Request $RelationalRequest) {
        $this->MapPropetiesToRelationalRequest($this->VerifyRelationalMap($EntityType), $RelationalRequest);
    }
    
    private function MapPropetiesToRelationalRequest(IEntityRelationalMap $EntityRelationalMap, Relational\Request $RelationalRequest, array $Properties = null) {
        if($Properties === null) {
            $Properties = $EntityRelationalMap->GetEntityMap()->GetProperties();
        }
        
        $DataPropertyColumnMappings = $EntityRelationalMap->GetDataPropertyColumnMappings();
        $EntityPropertyToOneRelationMappings = $EntityRelationalMap->GetEntityPropertyToOneRelationMappings();
        $CollectionPropertyToManyRelationMappings = $EntityRelationalMap->GetCollectionPropertyToManyRelationMappings();
        
        foreach($Properties as $PropertyIdentifier => $Property) {
            if(isset($DataPropertyColumnMappings[$PropertyIdentifier])) {
                $RelationalRequest->AddColumns($DataPropertyColumnMappings[$PropertyIdentifier]->GetReviveColumns());
            }
            else if(isset($EntityPropertyToOneRelationMappings[$PropertyIdentifier])) {
                $EntityPropertyToOneRelationMappings[$PropertyIdentifier]->AddToRelationalRequest($this, $RelationalRequest);
            }
            else if(isset($CollectionPropertyToManyRelationMappings[$PropertyIdentifier])) {
                $CollectionPropertyToManyRelationMappings[$PropertyIdentifier]->AddToRelationalRequest($this, $RelationalRequest);
            }
        }
    }
    
    private function MapObjectCriterion(Object\ICriterion $ObjectCriterion) {
        $EntityRelationalMap = $this->VerifyRelationalMap($ObjectCriterion->GetEntityType());
        
        $RelationalCriterion = $EntityRelationalMap->GetCriterion();
        $this->MapCriterion(
                $EntityRelationalMap, 
                $ObjectCriterion, 
                $RelationalCriterion);
        
        return $RelationalCriterion;
    }

    private function MapCriterion(IEntityRelationalMap $EntityRelationalMap,
            Object\ICriterion $ObjectCriterion, Relational\Criterion $RelationalCriterion) {
        
        if ($ObjectCriterion->IsConstrained()) {
            foreach ($this->MapExpressions($EntityRelationalMap, $ObjectCriterion->GetPredicateExpressions()) as $PredicateExpression) {
                $RelationalCriterion->AddPredicateExpression($PredicateExpression);
            }
        }
        
        if ($ObjectCriterion->IsOrdered()) {
            $ExpressionAscendingMap = $ObjectCriterion->GetOrderByExpressionsAscendingMap();
            
            foreach ($ExpressionAscendingMap as $Expression) {
                $IsAscending = $ExpressionAscendingMap[$Expression];
                $Expressions = $this->MapExpression($EntityRelationalMap, $Expression);
                foreach($Expressions as $Expression) {
                    $RelationalCriterion->AddOrderByExpression($Expression, $IsAscending);
                }
            }
        }
        
        if ($ObjectCriterion->IsGrouped()) {
            foreach ($this->MapExpressions($EntityRelationalMap, $ObjectCriterion->GetGroupByExpressions()) as $GroupByExpression) {
                $RelationalCriterion->AddGroupByExpression($GroupByExpression);
            }
        }
        
        if ($ObjectCriterion->IsRanged()) {
            $RelationalCriterion->SetRangeOffset($ObjectCriterion->GetRangeOffset());
            $RelationalCriterion->SetRangeAmount($ObjectCriterion->GetRangeAmount());
        }
    }
    
    private function MapExpressions(IEntityRelationalMap $EntityRelationalMap, array $Expressions) {
        return call_user_func_array('array_merge',
                array_map(
                        function ($Expression) use (&$EntityRelationalMap) {
                            return $this->MapExpression($EntityRelationalMap, $Expression);
                        }, $Expressions));
    }
    
    /**
     * @return Relational\Expressions\Expression[]
     */
    protected abstract function MapExpression(IEntityRelationalMap $EntityRelationalMap, Object\Expressions\Expression $Expression);
    // </editor-fold>
    
    // <editor-fold defaultstate="collapsed" desc="Entity Revival Helpers">
    /**
     * @internal
     * @return object[]
     */
    final public function ReviveEntities($EntityType, array $ResultRows) {
        $RevivalDataArray = $this->MapRowsToRevivalData($EntityType, $ResultRows);
        return $this->Domain->ReviveEntities($EntityType, $RevivalDataArray);
    }
    
    /**
     * @internal
     * @return Object\RevivalData[]
     */
    final public function MapRowsToRevivalData($EntityType, array $ResultRows) {
        $EntityRelationalMap = $this->VerifyRelationalMap($EntityType);

        $ResultRowRevivalDataMap = new Map();
        $RevivalDataArray = array();
        $EntityMap = $EntityRelationalMap->GetEntityMap();
        foreach ($ResultRows as $Key => $ResultRow) {
            $RevivalData = $EntityMap->RevivalData();
            $ResultRowRevivalDataMap[$ResultRow] = $RevivalData;
            $RevivalDataArray[$Key] = $RevivalData;
        }
        
        $this->MapResultRowsToRevivalData($EntityRelationalMap, $ResultRowRevivalDataMap);
        
        return $RevivalDataArray;
    }
    
    final public function MapResultRowsToRevivalData(IEntityRelationalMap $EntityRelationalMap, Map $ResultRowRevivalDataMap) {
        foreach($EntityRelationalMap->GetDataPropertyColumnMappings() as $PropertyColumnMapping) {
            $PropertyColumnMapping->Revive($ResultRowRevivalDataMap);
        }
        foreach($EntityRelationalMap->GetEntityPropertyToOneRelationMappings() as $EntityPropertyToOneRelationMapping) {
            $EntityPropertyToOneRelationMapping->Revive($this, $ResultRowRevivalDataMap);
        }
        foreach($EntityRelationalMap->GetCollectionPropertyToManyRelationMappings() as $CollectionPropertyToManyRelationMapping) {
            $CollectionPropertyToManyRelationMapping->Revive($this, $ResultRowRevivalDataMap);
        }
    }
    // </editor-fold>
    
    // <editor-fold defaultstate="collapsed" desc="Entity Persistence Helpers">

    private function MapUnitOfWorkToTransaction(
            Object\UnitOfWork $UnitOfWork, 
            Relational\Transaction $Transaction) {
        foreach($UnitOfWork->GetPersistenceDataGroups() as $EntityType => $PersistenceDataGroup) {
            $this->MapPersistenceDataToTransaction($UnitOfWork, $Transaction, $PersistenceDataGroup);
        }
        foreach($UnitOfWork->GetExecutedProcedures() as $Procedure) {
            $Transaction->Execute($this->MapProcedure($Procedure));
        }
        foreach($UnitOfWork->GetDiscardenceDataGroups() as $EntityType => $DiscardedIdentityGroup) {
            $EntityRelationalMap = $this->UnitOfWorkTransactionMapper[$EntityType];
            $ResultRows = $this->MapEntityDataToTransaction($UnitOfWork, $Transaction, $EntityRelationalMap, $DiscardedIdentityGroup);
            foreach($ResultRows as $ResultRow) {
                $Transaction->DiscardAll($ResultRow->GetPrimaryKeys());
            }            
        }
        foreach($UnitOfWork->GetDiscardedCriteria() as $DiscardedCriterion) {
            $Transaction->DiscardWhere($this->MapObjectCriterion($DiscardedCriterion));
        }
    }
    
    private function MapPersistenceDataToTransaction(
            Object\UnitOfWork $UnitOfWork, 
            Relational\Transaction $Transaction,
            array $PersistenceDataArray) {
        if(count($PersistenceDataArray) === 0) {
            return;
        }
        
        $EntityRelationalMap = $this->UnitOfWorkTransactionMapper[reset($PersistenceDataArray)->GetEntityType()];
        $PrimaryKeyTable = $EntityRelationalMap->GetPrimaryKeyTable();
        $ResultRows = $this->MapEntityDataToTransaction($UnitOfWork, $Transaction, $EntityRelationalMap, $PersistenceDataArray);
        
        foreach($ResultRows as $Key => $ResultRow) {
            $PersistenceData = $PersistenceDataArray[$Key];
            $Transaction->PersistAll($ResultRow->GetRows());
            
            $PrimaryKeyRow = $ResultRow->GetRow($PrimaryKeyTable);
            if(!$PrimaryKeyRow->HasPrimaryKey()) {
                $Transaction->SubscribeToPostPersistEvent(
                        $ResultRow->GetRow($PrimaryKeyTable), 
                        function ($PersistedRow) use (&$UnitOfWork, $PersistenceData, &$EntityRelationalMap) {
                            $Identity = $EntityRelationalMap->MapPrimaryKeyToIdentity($PersistedRow->GetPrimaryKey());
                            $UnitOfWork->SupplyIdentity($PersistenceData, $Identity);
                        });
            }
        }
        
        return $ResultRows;
    }
    
    private function MapEntityDataToTransaction(
            Object\UnitOfWork $UnitOfWork, Relational\Transaction $Transaction, 
            IEntityRelationalMap $EntityRelationalMap, array $EntityDataArray) {
        
        $DataPropertyColumnMappings = $EntityRelationalMap->GetDataPropertyColumnMappings();
        $EntityPropertyToOneRelationMappings = $EntityRelationalMap->GetEntityPropertyToOneRelationMappings();
        $CollectionPropertyToManyRelationMappings = $EntityRelationalMap->GetCollectionPropertyToManyRelationMappings();
        
        $ResultRowArray = array();
        foreach($EntityDataArray as $Key => $EntityData) {
            $ResultRowData = $EntityRelationalMap->ResultRow();
            
            foreach($DataPropertyColumnMappings as $DataPropertyColumnMapping) {
                $Property = $DataPropertyColumnMapping->GetProperty();
                if(isset($EntityData[$Property])) {
                    $DataPropertyValue = $EntityData[$Property];
                    $DataPropertyColumnMapping->Persist($DataPropertyValue, $ResultRowData);
                }
            }
            
            foreach($EntityPropertyToOneRelationMappings as $EntityPropertyToOneRelationMapping) {
                $RelationshipChange = $EntityData[$EntityPropertyToOneRelationMapping->GetProperty()];
                $MappedRelationshipChange = 
                        $this->MapRelationshipChanges($UnitOfWork, $Transaction, 
                        [$RelationshipChange])[0];
                $EntityPropertyToOneRelationMapping->Persist($Transaction, $ResultRowData, $MappedRelationshipChange);
            }
            
            foreach($CollectionPropertyToManyRelationMappings as $CollectionPropertyToManyRelationMapping) {
                $RelationshipChanges = $EntityData[$CollectionPropertyToManyRelationMapping->GetProperty()];
                $MappedRelationshipChanges = 
                        $this->MapRelationshipChanges($UnitOfWork, $Transaction, $RelationshipChanges);
                
                $CollectionPropertyToManyRelationMapping->Persist($Transaction, $ResultRowData, $MappedRelationshipChanges);
            }
            
            $ResultRowArray[$Key] = $ResultRowData;
        }
        
        return $ResultRowArray;
    }
    
    // <editor-fold defaultstate="collapsed" desc="Relationship Mapping Helpers">
    
    private function MapIdentityToPrimaryKey(Object\Identity $Identity) {
        $EntityRelationalMap = $this->VerifyRelationalMap($Identity->GetEntityType());
        return $EntityRelationalMap->MapIdentityToPrimaryKey($Identity);
    }
    
    private function MapPrimaryKeyToIdentity(Relational\PrimaryKey $PrimaryKey) {
        $EntityRelationalMap = $this->GetRelationMapByPrimaryKeyTable($PrimaryKey->GetTable()->GetName());
        return $EntityRelationalMap->MapPrimaryKeyToIdentity($PrimaryKey);
    }
    
    /**
     * @internal
     * @return Relational\DiscardedRelationship
     */
    final public function MapDiscardedRelationships(array $ObjectDiscardedRelationships) {
        $RelationalDiscardedRelationships = array();
        foreach($ObjectDiscardedRelationships as $Key => $DiscardedRelationship) {
            if($DiscardedRelationship === null) {
                $RelationalDiscardedRelationships[$Key] = null;
                continue;
            }
            $ParentPrimaryKey = $this->MapIdentityToPrimaryKey($DiscardedRelationship->GetParentIdentity());
            $ChildPrimaryKey = $this->MapIdentityToPrimaryKey($DiscardedRelationship->GetRelatedIdentity());
            
            $RelationalDiscardedRelationships[$Key] = new Relational\DiscardedRelationship($ParentPrimaryKey, $ChildPrimaryKey);
        }
        
        return $RelationalDiscardedRelationships; 
    }


    /**
     * @internal
     * @return Relational\PersistedRelationship
     */
    final public function MapPersistedRelationships(
            Object\UnitOfWork $UnitOfWork, Relational\Transaction $Transaction,             
            array $ObjectPersistedRelationships) {
        
        $ParentPrimaryKey = null;
        $ChildPersistenceData = array();
        foreach($ObjectPersistedRelationships as $Key => $ObjectPersistedRelationship) {
            if($ObjectPersistedRelationship === null) {
                continue;
            }
            if($ParentPrimaryKey === null) {
                $ParentPrimaryKey = $this->MapIdentityToPrimaryKey(
                        $ObjectPersistedRelationship->GetParentIdentity());
            }            
            if ($ObjectPersistedRelationship->IsIdentifying()) {
                $ChildPersistenceData[$Key] = $ObjectPersistedRelationship->GetChildPersistenceData();
            }
        }
        $ChildResultRows = $this->MapPersistenceDataToTransaction($UnitOfWork, $Transaction, $ChildPersistenceData);
        

        $RelationalPersistedRelationships = array();
        foreach($ObjectPersistedRelationships as $Key => $ObjectPersistedRelationship) {
            if($ObjectPersistedRelationship === null) {
                $RelationalPersistedRelationships[$Key] = null;
                continue;
            }
            if ($ObjectPersistedRelationship->IsIdentifying()) {
                $RelationalPersistedRelationships[$Key] = 
                        new Relational\PersistedRelationship($ParentPrimaryKey, null, $ChildResultRows[$Key]);
            }
            else {
                $RelatedPrimaryKey = $this->MapIdentityToPrimaryKey($ObjectPersistedRelationship->GetRelatedIdentity());
                $RelationalPersistedRelationships[$Key] = 
                        new Relational\PersistedRelationship($ParentPrimaryKey, $RelatedPrimaryKey, null);
            }
        }
        
        return $RelationalPersistedRelationships;
    }


    /**
     * @internal
     * @return Relational\RelationshipChange
     */
    final public function MapRelationshipChanges(
            Object\UnitOfWork $UnitOfWork, Relational\Transaction $Transaction,
            array $ObjectRelationshipChanges) {
        
        $ObjectPersistedRelationships = array();
        $ObjectDiscardedRelationships = array();
        
        foreach($ObjectRelationshipChanges as $Key => $ObjectRelationshipChange) {
            $ObjectPersistedRelationships[$Key] = $ObjectRelationshipChange->GetPersistedRelationship();
            $ObjectDiscardedRelationships[$Key] = $ObjectRelationshipChange->GetDiscardedRelationship();
        }
        
        $RelationalPersistedRelationships = $this->MapPersistedRelationships($UnitOfWork, $Transaction, 
                $ObjectPersistedRelationships);
        $RelationalDiscardedRelationships = $this->MapDiscardedRelationships($ObjectDiscardedRelationships);
        
        $RelationalRelationshipChanges = array();
        foreach($ObjectRelationshipChanges as $Key => $ObjectRelationshipChange) {
            $RelationalRelationshipChanges[$Key] = new Relational\RelationshipChange(
                    $RelationalPersistedRelationships[$Key], $RelationalDiscardedRelationships[$Key]);
        }
        
        return $RelationalRelationshipChanges;
    }

    // </editor-fold>
    // </editor-fold>
}

?>