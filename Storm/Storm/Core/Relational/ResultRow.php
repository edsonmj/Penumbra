<?php

namespace Storm\Core\Relational;

/**
 * This class represents the data of column from one or many tables.
 * 
 * @author Elliot Levin <elliot@aanet.com.au>
 */
class ResultRow extends ColumnData {
    /**
     * @var Table[] 
     */
    private $Tables = array();
    
    /**
     * @var Row[] 
     */
    private $Rows = array();
    
    /**
     * @var PrimaryKey[] 
     */
    private $PrimaryKeys = array();
    public function __construct(array $Columns, array $ColumnData = array()) {
        foreach($Columns as $Column) {
            $Table = $Column->GetTable();
            $TableName = $Table->GetName();
            
            $this->Tables[$TableName] = $Table;
            if(!isset($this->Rows[$TableName])) {
                $this->Rows[$TableName] = new Row($Table, 
                        array_intersect_key($ColumnData, $Table->GetColumnIdentifiers()));
                $this->PrimaryKeys[$TableName] = $this->Rows[$TableName]->GetPrimaryKey();
            }
        }
        
        parent::__construct($Columns, $ColumnData);
    }
    
    protected function AddColumn(IColumn $Column, $Data) {
        parent::AddColumn($Column, $Data);
        
        $this->Rows[$Column->GetTable()->GetName()][$Column] = $Data;
    }
    
    protected function RemoveColumn(IColumn $Column) {
        parent::RemoveColumn($Column);
        
        unset($this->Rows[$Column->GetTable()->GetName()][$Column]);
    }
    
    public function __clone() {
        foreach($this->Rows as $TableName => $Rows) {
            $this->Rows[$TableName] = clone $this->Rows[$TableName];
            $this->PrimaryKeys[$TableName] = $this->Rows[$TableName]->GetPrimaryKey();
        }
    }
    
    /**
     * @return Table[]
     */
    final public function GetTables() {
        return $this->Tables;
    }
    
    final public function IsOf(Table $Table) {
        return isset($this->Tables[$Table->GetName()]);
    }
    
    /**
     * @return Row[]
     */
    final public function GetRows() {
        return $this->Rows;
    }
    
    /**
     * @return PrimaryKeys[]
     */
    final public function GetPrimaryKeys() {
        return $this->PrimaryKeys;
    }
    
    /**
     * Get a the row of the supplied table
     * 
     * @param Table $Table The table of the row to retreive
     * @return Row The matching row
     * @throws \InvalidArgumentException If the table is not part of this result row
     */
    final public function GetRow(Table $Table) {
        if(!$this->IsOf($Table)) {
            throw new \InvalidArgumentException('$Table must be a part of this row');
        }
        
        return $this->Rows[$Table->GetName()];
    }
    
    /**
     * Get a the primary key of the supplied table
     * 
     * @param Table $Table The table of the primary key to retreive
     * @return PrimaryKey The matching primary key
     * @throws \InvalidArgumentException If the table is not part of this result row
     */
    final public function GetPrimaryKey(Table $Table) {
        if(!$this->IsOf($Table)) {
            throw new \InvalidArgumentException('$Table must be a part of this row');
        }
        
        return $this->PrimaryKeys[$Table->GetName()];
    }
    
    /**
     * Gets the data from the supplied columns
     * 
     * @param IColumn[] $Columns The columns to get the from
     * @return ResultRow The data of the supplied columns
     */
    final public function GetDataFromColumns(array $Columns) {
        $ColumnData = $this->GetColumnData();
        $ColumnIdentifiers = 
                array_flip(array_map(function ($Column) { return $Column->GetIdentifier(); }, $Columns));
        
        return new ResultRow($Columns, array_intersect_key($ColumnData, $ColumnIdentifiers));
    }
    
    
    /**
     * Gets the data from the supplied columns from all of the supplied result rows
     * NOTE: Keys are preserved.
     * 
     * @param ResultRow[] $ResultRows The result rows to get the data from
     * @param IColumn[] $Columns The columns to get the from
     * @return ResultRow[] The data of the supplied columns
     */
    final public static function GetAllDataFromColumns(array $ResultRows, array $Columns) {
        $NewResultRow = new ResultRow($Columns);
        $ColumnIdentifiers = array_flip(array_keys($NewResultRow->GetColumns()));
        
        $NewResultRows = array();
        foreach($ResultRows as $Key => $ResultRow) {
            $NewResultRows[$Key] = $NewResultRow->Another(
                    array_intersect_key($ResultRow->GetColumnData(), $ColumnIdentifiers));
        }
        
        return $NewResultRows;
    }
}

?>