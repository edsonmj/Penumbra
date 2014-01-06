<?php

namespace Storm\Drivers\Base\Object\Properties\Accessors;

class InvocationBase {
    protected $ConstantArguments;
    /**
     *
     * @var \ReflectionMethod 
     */
    protected $Reflection;
    public function __construct(array $ConstantArguments = array()) {
        $this->ConstantArguments = $ConstantArguments;
    }

    final public function Identifier(&$Identifier) {
        $Identifier .= $this->Reflection->getFileName() . $this->Reflection->getStartLine() . $this->Reflection->getEndLine();
    }

    final public function SetEntityType($EntityType) {
        if(!method_exists($EntityType, '__invoke')) {
            throw new \Exception();
        }
        $this->Reflection = new \ReflectionMethod($EntityType, '__invoke');
    }
}

?>