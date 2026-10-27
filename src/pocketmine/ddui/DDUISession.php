<?php

declare(strict_types=1);

namespace pocketmine\ddui;

use pocketmine\network\mcpe\protocol\types\DataStoreUpdate;

class DDUISession{

    private ?DDUI $active = null;
	
    private int $nextId = 0;
	
    private float $windowStart = 0.0;
	
    private int $writes = 0;

    public function attach(DDUI $form) : int{
        if($this->active !== null){
			throw new \LogicException("Close the current DDUI form, then open the next one on a later tick");
		}
		
        if($this->nextId >= 2147483647){
			throw new \OverflowException("DDUI form IDs exhausted");
		}
		
        $this->active = $form;
        return ++$this->nextId;
    }
	
    public function detach(DDUI $form) : void{
        if($this->active === $form){
			$this->active = null;
		}
    }
	
    public function update(DataStoreUpdate $update) : bool{
        $now = microtime(true);
		
        if($now - $this->windowStart >= 1){
			$this->windowStart = $now;
			$this->writes = 0;
		}
		
        if(++$this->writes <= 60 && $this->active !== null){
			$this->active->handleUpdate($update);
		}
        return true;
    }
	
    public function closed(int $id, string $reason) : bool{
        if($this->active !== null && $this->active->getId() === $id){
            $form = $this->active;
			$this->active = null;
			$form->finish($reason);
        }
        return true;
    }
	
    public function dispose() : void{
        $form = $this->active;
		$this->active = null;
		
        if($form !== null){
			$form->release();
		}
    }
}