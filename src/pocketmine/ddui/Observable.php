<?php

declare(strict_types=1);

namespace pocketmine\ddui;

use function is_int;
use function is_float;
use function is_finite;
use function gettype;

class Observable{
	
	private string|bool|int|float $value;
	
	private bool $clientWritable;

    private array $listeners = [];
	
    private int $nextId = 0;

    public function __construct(string|bool|int|float $value, bool $clientWritable = false){
		$this->value = $value;
		$this->clientWritable = $clientWritable;
	}

    public function getData() : string|bool|int|float{
		return $this->value;
	}

    public function isClientWritable() : bool{
		return $this->clientWritable;
	}

    public function setData(string|bool|int|float $value) : void{
        if(is_int($this->value) || is_float($this->value)){
            if(!is_int($value) && !is_float($value)){
				throw new \InvalidArgumentException("Expected a number");
			}
			
            if(!is_finite((float) $value)){
				throw new \InvalidArgumentException("Expected a finite number");
			}
        }elseif(gettype($this->value) !== gettype($value)){
            throw new \InvalidArgumentException("Observable type cannot change");
        }
		
        if($this->value === $value){
			return;
		}
		
        $this->value = $value;
		
        foreach($this->listeners as $listener){
			$listener($value);
		}
    }
	
    public function subscribe(\Closure $listener) : \Closure{
        $id = ++$this->nextId;
        $this->listeners[$id] = $listener;
		
        return function() use ($id) : void{ unset($this->listeners[$id]); };
    }
}