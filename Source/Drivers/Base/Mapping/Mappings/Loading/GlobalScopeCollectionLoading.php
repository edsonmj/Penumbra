<?php

namespace Penumbra\Drivers\Base\Mapping\Mappings\Loading;

use \Penumbra\Core\Mapping;
use \Penumbra\Core\Object;
use \Penumbra\Core\Relational;

class GlobalScopeCollectionLoading extends CollectionLoading {
    private $CurrentLoadIndex = 0;
    private $ParentRowArrays = [];
    private $RelatedRowArrays = [];

    public function Load(
            Mapping\IEntityRelationalMap $EntityRelationalMap, 
            Relational\Database $Database, 
            Relational\IToManyRelation $ToManyRelation, 
            array $ParentRowArray) {
        $this->ParentRowArrays[] = $ParentRowArray;
        
        $RelatedRevivalDataArrayLoader = function ($ParentRowKey) 
                use (&$EntityRelationalMap, &$Database, &$ToManyRelation, &$ParentRowArray) {  
            static $ParentRowRelatedRevivalDataArrayMap = null;
            
            $LoadIndex = $this->CurrentLoadIndex;
            if($ParentRowRelatedRevivalDataArrayMap === null) {
                $ParentRowRelatedRevivalDataArrayMap = $this->LoadAllRelatedRows(
                        $EntityRelationalMap, 
                        $Database, 
                        $ToManyRelation, 
                        $ParentRowArray, 
                        $LoadIndex);
            }
            
            return $ParentRowRelatedRevivalDataArrayMap[$ParentRowKey];
        };
        
        return $this->MapParentRowKeysToMultipleLazyRevivalData(
                $EntityRelationalMap, 
                $ToManyRelation, 
                $ParentRowArray, 
                $RelatedRevivalDataArrayLoader);
    }
    
    private function LoadAllRelatedRows(
            Mapping\IEntityRelationalMap $EntityRelationalMap, 
            Relational\Database $Database, 
            Relational\IToManyRelation $ToManyRelation, 
            array $ParentRowArray,
            $LoadIndex) {
        
        if(count($this->ParentRowArrays) > 0 && !isset($this->RelatedRowArrays[$LoadIndex])) {
            $AllParentRows = call_user_func_array('array_merge', $this->ParentRowArrays);
            
            $this->RelatedRowArrays[$this->CurrentLoadIndex] =& $this->LoadRelatedRows(
                    $EntityRelationalMap,
                    $ToManyRelation,
                    $Database,
                    $AllParentRows);
            $this->ParentRowArrays = [];
            $this->CurrentLoadIndex++;
        }
        
        return $this->MapParentRowKeysToRelatedRevivalDataArray(
                $EntityRelationalMap, 
                $ToManyRelation, 
                $ParentRowArray, 
                $this->RelatedRowArrays[$LoadIndex]);
    }
}

?>