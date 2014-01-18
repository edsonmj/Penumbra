<?php

namespace Storm\Core\Object;

/**
 * The base for a type representing a property of an entity.
 * 
 * @author Elliot Levin <elliot@aanet.com.au>
 */
interface IProperty {
    const IPropertyType = __CLASS__;
    
    /**
     * The value identifier for the property.
     * 
     * @return string
     */
    public function GetIdentifier();
    
    /**
     * The parent entity map.
     * 
     * @return EntityMap|null
     */
    public function GetEntityMap();
    
    /**
     * @return boolean
     */
    public function HasEntityMap();
    
    /**
     * Sets the parent entity map.
     * 
     * @param EntityMap|null $EntityMap
     * @return void
     */
    public function SetEntityMap(EntityMap $EntityMap = null);
}

?>