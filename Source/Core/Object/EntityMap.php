<?php

namespace Penumbra\Core\Object;

use \Penumbra\Core\Containers\Registrar;
use \Penumbra\Core\Containers\Map;
use \Penumbra\Core\Mapping\DomainDatabaseMap;

/**
 *{@inheritDoc}
 */
abstract class EntityMap implements IEntityMap {
    use \Penumbra\Core\Helpers\Type;   
    
    /**
     * @var string
     */
    private $EntityType;
    
    /**
     * The properties of entity.
     * 
     * @var IProperty[] 
     */
    private $Properties = [];
    
    /**
     * The properties containing the entity data.
     * 
     * @var IDataProperty[] 
     */
    private $DataProperties = [];
    
    /**
     * The properties representing related entities.
     * 
     * @var IRelationshipProperty[] 
     */
    private $RelationshipProperties = [];
    
    /**
     * The properties containing a related entity.
     * 
     * @var IEntityProperty[] 
     */
    private $EntityProperties = [];
    
    /**
     * The properties containing the related entity collections.
     * 
     * @var ICollectionProperty[] 
     */
    private $CollectionProperties = [];
    
    /**
     * The properties containing the entity's identity.
     * 
     * @var IDataProperty[] 
     */
    private $IdentityProperties = [];
    
    public function __construct() {
        $this->EntityType = $this->EntityType();
    }
    
    /**
     * {@inheritDoc}
     */
    final public function InititalizeProperties(Domain $Domain) {
        $this->OnInitialize($Domain);
        $Registrar = new Registrar(IProperty::IPropertyType);
        $this->RegisterProperties($Domain, $Registrar);
        foreach($Registrar->GetRegistered() as $Property) {
            $this->AddProperty($Property);
        }
        $this->OnInitialized($Domain);
    }
    protected function OnInitialize(Domain $Domain) { }
    protected function OnInitialized(Domain $Domain) { }
    
    /**
     * This method should be implemented such that it returns the type of entity
     * that this map represents.
     * 
     * @return string
     */
    protected abstract function EntityType();
    
    /**
     * This method should be implemented such that it registers the properties of 
     * the entity.
     * 
     * @return void
     */
    protected abstract function RegisterProperties(Domain $Domain, Registrar $Registrar);
    
    /**
     * Adds a property to the given entity map.
     * 
     * @param \Penumbra\Core\Object\IProperty $Property The property to add
     * @return void
     * @throws InvalidPropertyException
     */
    private function AddProperty(IProperty $Property) {
        $Property->SetEntityType($this->EntityType);
        $Identifier = $Property->GetIdentifier();
        
        if($Property instanceof IDataProperty) {
            $this->DataProperties[$Identifier] = $Property;
            if($Property->IsIdentity()) {
                $this->IdentityProperties[$Identifier] = $Property;
            }
        }
        else if($Property instanceof IEntityProperty) {
            $this->EntityProperties[$Identifier] = $Property;
        }
        else if($Property instanceof ICollectionProperty) {
            $this->CollectionProperties[$Identifier] = $Property;
        }
        else {
            throw new InvalidPropertyException(
                    'The supplied property must be of type %s, %s, %s: %s given',
                    IDataProperty::IDataPropertyType,
                    IEntityProperty::IEntityPropertyType,
                    ICollectionProperty::ICollectionPropertyType,
                    get_class($Property));
        }
        if($Property instanceof IRelationshipProperty) {
            $this->RelationshipProperties[$Identifier] = $Property;
        }
        
        $this->Properties[$Identifier] = $Property;
    }
    
    final public function InitializeRelationshipProperties(Domain $Domain) {
        foreach($this->RelationshipProperties as $RelationshipProperty) {
            $RelatedEntityMap = $Domain->GetEntityMap($RelationshipProperty->GetRelatedEntityType());
            $RelationshipProperty->SetRelatedEntityMap($RelatedEntityMap);
        }
    }
    
    /**
     * Verifies an object to be of the type represented by this entity map.
     * 
     * @param object $Entity The entity to verify
     * @throws TypeMismatchException
     */
    private function VerifyEntity($Method, $Entity) {
        if(!($Entity instanceof $this->EntityType)) {
            throw new TypeMismatchException(
                    'The supplied entity to %s must be of the type %s: %s given',
                    $Method,
                    $this->EntityType,
                    \Penumbra\Utilities\Type::GetTypeOrClass($Entity));
        }
    }
    
    /**
     * {@inheritDoc}
     */
    final public function HasProperty(IProperty $Property) {
        return isset($this->Properties[$Property->GetIdentifier()]);
    }
    
    /**
     * {@inheritDoc}
     */
    final public function HasIdentityProperty(IProperty $Property) {
        return isset($this->IdentityProperties[$Property->GetIdentifier()]);
    }
    
    /**
     * {@inheritDoc}
     */
    final public function HasRelationshipProperty(IProperty $Property) {
        return isset($this->RelationshipProperties[$Property->GetIdentifier()]);
    }
    
    /**
     * {@inheritDoc}
     */
    final public function GetProperty($Identifier) {
        return isset($this->Properties[$Identifier]) ? $this->Properties[$Identifier] : null;
    }
    
    /**
     * @return IProperty[]
     */
    final public function GetIdentityProperties() {
        return $this->IdentityProperties;
    }
    
    /**
     * {@inheritDoc}
     */
    final public function GetEntityType() {
        return $this->EntityType;
    }
    
    /**
     * {@inheritDoc}
     */
    final public function Is(IEntityMap $OtherEntityMap) {
        return $this->EntityType === $OtherEntityMap->EntityType;
    }
    
    /**
     * {@inheritDoc}
     */
    final public function GetProperties() {
        return $this->Properties;
    }
    
    /**
     * {@inheritDoc}
     */
    final public function GetDataProperties() {
        return $this->DataProperties;
    }
    
    /**
     * {@inheritDoc}
     */
    final public function GetRelationshipProperties() {
        return $this->RelationshipProperties;
    }
    
    /**
     * {@inheritDoc}
     */
    final public function GetEntityProperties() {
        return $this->EntityProperties;
    }
    
    /**
     * {@inheritDoc}
     */
    final public function GetCollectionProperties() {
        return $this->CollectionProperties;
    }
    
    /**
     * {@inheritDoc}
     */
    public function ResolveTraversalExpression(Expressions\TraversalExpression $Expression) {
        foreach ($this->Properties as $Property) {
            $ResolvedPropertyExpression = $Property->ResolveTraversalExpression($Expression);
            if($ResolvedPropertyExpression !== null) {
                return $ResolvedPropertyExpression;
            }
        }
    }
    
    /**
     * {@inheritDoc}
     */
    public abstract function ConstructEntity(RevivalData $RevivalData);
    
    /**
     * {@inheritDoc}
     */
    final public function HasIdentity($Entity) {
        foreach($this->Identity($Entity)->GetData() as $Value) {
            if($Value === null) {
                return false;
            }
        }
        return true;
    }
    
    private $Identity = null;
    /**
     * {@inheritDoc}
     */
    final public function Identity($Entity = null) {
        if($this->Identity === null) {
            $this->Identity = new Identity($this);
        }
        
        $IdentityData = [];
        if($Entity !== null) {
            $this->VerifyEntity(__METHOD__, $Entity);
            foreach($this->IdentityProperties as $Identifier => $IdentityProperty) {
                $IdentityData[$Identifier] = $IdentityProperty->GetValue($Entity);
            }
        }
        
        return $this->Identity->Another($IdentityData);
    }
    
    /**
     * @var RevivalData
     */
    private $RevialData = null;
    /**
     * {@inheritDoc}
     */
    final public function RevivalData(array $RevivalData = []) {
        if($this->RevialData === null) {
            $this->RevialData = new RevivalData($this);
        }
        return $this->RevialData->Another($RevivalData);
    }
    
    /**
     * @var PersistenceData
     */
    private $PersistenceData = null;
    /**
     * {@inheritDoc}
     */
    final public function PersistanceData($Entity, array $PersistanceData = []) {
        if($this->PersistenceData === null) {
            $this->PersistenceData = new PersistenceData($this, null);
        }
        return $this->PersistenceData->AnotherPersistenceData($Entity, $PersistanceData);
    }
    
    /**
     * @var DiscardenceData
     */
    private $DiscardenceData = null;
    /**
     * {@inheritDoc}
     */
    final public function DiscardenceData(array $DiscardenceData = []) {
        if($this->DiscardenceData === null) {
            $this->DiscardenceData = new DiscardenceData($this);
        }
        return $this->DiscardenceData->Another($DiscardenceData);
    }
    
    /**
     * {@inheritDoc}
     */
    final public function Persist(UnitOfWork $UnitOfWork, $Entity, PersistenceData $PersistenceData = null) {
        $this->VerifyEntity(__METHOD__, $Entity);
        
        $PersistenceDataArray = [];
        foreach($this->DataProperties as $Identifier => $DataProperty) {
            $PersistenceDataArray[$Identifier] = $DataProperty->GetValue($Entity);
        }
        foreach($this->EntityProperties as $Identifier => $EntityProperty) {
            $PersistenceDataArray[$Identifier] = $EntityProperty->Persist($UnitOfWork, $Entity);
        }
        foreach($this->CollectionProperties as $Identifier => $CollectionProperty) {
            $PersistenceDataArray[$Identifier] = $CollectionProperty->Persist($UnitOfWork, $Entity);
        }
        
        if($PersistenceData === null) {
            return new PersistenceData($this, $Entity, $PersistenceDataArray);
        }
        else {
            $PersistenceData->SetData($PersistenceDataArray);
            return $PersistenceData;
        }
    }
    
    /**
     * {@inheritDoc}
     */
    final public function PersistRelationships(UnitOfWork $UnitOfWork, $Entity, PersistenceData $PersistenceData = null) {
        $this->VerifyEntity(__METHOD__, $Entity);
        
        $PersistenceDataArray = [];
        foreach($this->EntityProperties as $Identifier => $EntityProperty) {
            $PersistenceDataArray[$Identifier] = $EntityProperty->Persist($UnitOfWork, $Entity);
        }
        foreach($this->CollectionProperties as $Identifier => $CollectionProperty) {
            $PersistenceDataArray[$Identifier] = $CollectionProperty->Persist($UnitOfWork, $Entity);
        }
        
        if($PersistenceData === null) {
            return new PersistenceData($this, $Entity, $PersistenceDataArray);
        }
        else {
            $PersistenceData->SetData($PersistenceDataArray);
            return $PersistenceData;
        }
    }
    
    /**
     * {@inheritDoc}
     */
    final public function Discard(UnitOfWork $UnitOfWork, $Entity, DiscardenceData $DiscardenceData = null) {
        $this->VerifyEntity(__METHOD__, $Entity);
        
        $DiscardenceDataArray = [];
        foreach($this->IdentityProperties as $Identifier => $IdentityProperty) {
            $DiscardenceDataArray[$Identifier] = $IdentityProperty->GetValue($Entity);
        }
        foreach($this->EntityProperties as $Identifier => $EntityProperty) {
            $DiscardenceDataArray[$Identifier] = $EntityProperty->Discard($UnitOfWork, $Entity);
        }
        foreach($this->CollectionProperties as $Identifier => $CollectionProperty) {
            $DiscardenceDataArray[$Identifier] = $CollectionProperty->Discard($UnitOfWork, $Entity);
        }
        
        if($DiscardenceData === null) {
            return new DiscardenceData($this, $DiscardenceDataArray);
        }
        else {
            $DiscardenceData->SetData($DiscardenceDataArray);
            return $DiscardenceData;
        }
    }
    
    /**
     * {@inheritDoc}
     */
    final public function Apply($Entity, PropertyData $PropertyData) {
        foreach($PropertyData as $PropertyIdentifier => $Value) {
            if(isset($this->DataProperties[$PropertyIdentifier])) {
                $this->DataProperties[$PropertyIdentifier]->ReviveValue($Value, $Entity);
            }
            else if(isset($this->EntityProperties[$PropertyIdentifier])) {
                $this->EntityProperties[$PropertyIdentifier]->Revive($Value, $Entity);
            }
            else if(isset($this->CollectionProperties[$PropertyIdentifier])) {
                $this->CollectionProperties[$PropertyIdentifier]->Revive($Value, $Entity);
            }
        }
    }
    
    /**
     * {@inheritDoc}
     */
    final public function ReviveEntities(array $RevivalDatas) {
        $Entities = [];
        foreach($RevivalDatas as $Key => $RevivalData) {
            $Entity = $this->ConstructEntity($RevivalData);
            $this->Apply($Entity, $RevivalData);
            $Entities[$Key] = $Entity;
        }
        
        return $Entities;
    }
    
    /**
     * {@inheritDoc}
     */
    final public function LoadEntity(RevivalData $RevivalData, $Entity) {
        $this->Apply($Entity, $RevivalData);
    }
}

?>