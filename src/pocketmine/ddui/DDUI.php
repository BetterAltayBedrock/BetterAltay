<?php

declare(strict_types=1);

namespace pocketmine\ddui;

use pocketmine\Player;

use pocketmine\network\mcpe\protocol\types\BoolDataStoreValue;
use pocketmine\network\mcpe\protocol\types\DoubleDataStoreValue;
use pocketmine\network\mcpe\protocol\types\StringDataStoreValue;
use pocketmine\network\mcpe\protocol\types\DataStoreRemoval;
use pocketmine\network\mcpe\protocol\types\DataStoreUpdate;
use pocketmine\network\mcpe\protocol\types\DataStoreChange;
use pocketmine\network\mcpe\protocol\ClientboundDataDrivenUICloseScreenPacket;
use pocketmine\network\mcpe\protocol\ClientboundDataDrivenUIShowScreenPacket;
use pocketmine\network\mcpe\protocol\ClientboundDataStorePacket;

use function abs;
use function floor;
use function round;
use function count;
use function strlen;
use function is_int;
use function is_string;
use function is_bool;
use function is_finite;
use function array_keys;
use function array_merge;
use function array_values;
use function preg_replace;
use function str_starts_with;
use function substr;

class DDUI{
	
    private array $fields = [];
	
    private array $inputs = [];
	
    private array $buttons = [];
	
    private array $unsubscribers = [];
	
    private array $counts = [];
	
    private ?string $receivingPath = null;
	
    private int $length = 0;
	
    private ?int $id = null;
	
    private ?\Closure $closed = null;

    public function __construct(private Player $player, string|Observable $title){
        $this->fields["title"] = $title;
        $this->fields["layout.length"] = 0;
    }

    private static function value(mixed $value) : mixed{
        return $value instanceof Observable ? $value->getData() : $value;
    }

    private function editable() : void{
        if($this->id !== null){
			throw new \LogicException("Cannot change form structure while showing");
		}
    }

    private function add(string $kind, array $fields, array $options) : string{
        $this->editable();

        if($this->length >= 128){
			throw new \InvalidArgumentException("Too many DDUI controls");
		}

        $path = "layout." . $this->length++;
        $this->fields["layout.length"] = $this->length;
        $fields["visible"] = $options["visible"] ?? true;
        $fields[$kind . "_visible"] = $options["visible"] ?? true;
        $fields["disabled"] = $options["disabled"] ?? false;
        $fields["description"] = $options["description"] ?? "";

        foreach($fields as $key => $value){
			$this->fields[$path . "." . $key] = $value;
		}

        return $path;
    }

    public function label(string|Observable $text, array $options = []) : self{
        $this->add("label", ["text" => $text], $options);
		return $this;
    }

    public function header(string|Observable $text, array $options = []) : self{
        $this->add("header", ["text" => $text], $options);
		return $this;
    }

    public function spacer(array $options = []) : self{
        $this->add("spacer", [], $options);
		return $this;
    }

    public function button(string|Observable $label, \Closure $callback, array $options = []) : self{
        $p = $this->add("button", ["label" => $label, "tooltip" => $options["tooltip"] ?? "", "onClick" => 0], $options);
        $this->buttons[$p . ".onClick"] = [$p, $callback];
		return $this;
    }

    public function closeButton(string|Observable $label = "Close") : self{
        $this->editable();

        foreach(["label" => $label, "visible" => true, "button_visible" => true, "disabled" => false, "onClick" => 0] as $k => $v){
            $this->fields["closeButton." . $k] = $v;
        }

        $this->buttons["closeButton.onClick"] = ["closeButton", function() : void{
			$this->close();
		}];

        return $this;
    }

    private function input(string $kind, string|Observable $label, string $key, Observable $value, array $options, array $extra, \Closure $validate) : self{
        if(!$value->isClientWritable()){
			throw new \InvalidArgumentException("Input observable must be client-writable");
		}

        if(!$validate($value->getData())){
			throw new \InvalidArgumentException("Invalid initial control value");
		}

        $p = $this->add($kind, array_merge(["label" => $label, $key => $value], $extra), $options);
        $this->inputs[$p . "." . $key] = [$p, $value, $validate];
		return $this;
    }

    public function toggle(string|Observable $label, Observable $value, array $options = []) : self{
        return $this->input("toggle", $label, "toggled", $value, $options, [], static fn($v) => is_bool($v));
    }

    public function textField(string|Observable $label, Observable $value, array $options = []) : self{
        $max = $options["maxBytes"] ?? 1024;

        if(!is_int($max) || $max < 1 || $max > 32768){
			throw new \InvalidArgumentException("Invalid maxBytes");
		}

        return $this->input("textfield", $label, "text", $value, $options, [], static fn($v) => is_string($v) && strlen($v) <= $max);
    }

    public function slider(string|Observable $label, Observable $value, int $min, int $max, int $step = 1, array $options = []) : self{
        if($max < $min || $step < 1){
			throw new \InvalidArgumentException("Invalid slider range");
		}

        return $this->input(
			"slider",
			$label,
			"value",
			$value,
			$options,
			["minValue" => $min, "maxValue" => $max, "step" => $step],
            static fn($v) => (is_int($v) || is_float($v)) && is_finite((float)$v) && $v >= $min && $v <= $max && abs(($v - $min) / $step - round(($v - $min) / $step)) < 0.000001
		);
    }

    public function dropdown(string|Observable $label, Observable $value, array $labels, array $options = []) : self{
        if(count($labels) < 1 || count($labels) > 128){
			throw new \InvalidArgumentException("Invalid dropdown size");
		}

        $extra = ["items.length" => count($labels)];

        foreach(array_values($labels) as $i => $item){
            $text = is_array($item) ? ($item["label"] ?? null) : $item;
            $description = is_array($item) ? ($item["description"] ?? "") : "";
            if(!is_string($text) || !is_string($description)){
				throw new \InvalidArgumentException("Dropdown labels and descriptions must be strings");
			}

            $extra["items." . $i . ".label"] = $text;
            $extra["items." . $i . ".description"] = $description;
            $extra["items." . $i . ".value"] = $i;
        }

        $count = count($labels);

        return $this->input(
			"dropdown",
			$label,
			"value",
			$value,
			$options,
			$extra,
            static fn($v) => (is_int($v) || is_float($v)) && is_finite((float)$v) && floor($v) === (float)$v && $v >= 0 && $v < $count
		);
    }

    public function onClose(\Closure $callback) : self{
		$this->closed = $callback;
		return $this;
	}

    public function isShowing() : bool{
		return $this->id !== null;
	}

    public function getId() : ?int{
		return $this->id;
	}

    private function property() : string{
		return "custom_form_data_" . $this->id;
	}

    private function storePrefix() : string{
        return "minecraft:" . $this->property();
    }

    private static function storeNameForProperty(string $property, string $path) : string{
        return "minecraft:" . $property . "." . (preg_replace("/\.(\d+)(?=\.|$)/", "[$1]", $path) ?? $path);
    }

    private function storeName(string $path) : string{
        return self::storeNameForProperty($this->property(), $path);
    }

    private function pathFromStoreName(string $name) : ?string{
        $prefix = $this->storePrefix() . ".";
        $propertyPrefix = $this->property() . ".";

        if(str_starts_with($name, $prefix)){
            $path = substr($name, strlen($prefix));
        }elseif(str_starts_with($name, $propertyPrefix)){
            $path = substr($name, strlen($propertyPrefix));
        }else{
            return null;
        }

        return preg_replace("/\[(\d+)]/", ".$1", $path);
    }

    /** @return DataStoreChange[] */
    private function createDataStoreChanges() : array{
        $changes = [];

        foreach($this->fields as $path => $value){
            $value = self::value($value);
            $data = match(true){
                is_bool($value) => new BoolDataStoreValue($value),
                is_string($value) => new StringDataStoreValue($value),
                is_int($value), is_float($value) => new DoubleDataStoreValue((float) $value),
                default => throw new \InvalidArgumentException("DDUI values must be scalar")
            };

            $changes[] = new DataStoreChange($this->storeName($path), 1, $data);
        }

        return $changes;
    }

    public function show() : void{
        $this->editable();

        if(!$this->player->isOnline()){
			throw new \LogicException("Player is not online");
		}

        foreach($this->buttons as $path => $_){
			$this->fields[$path] = 0;
		}

        $session = $this->player->getDDUISession();
        $this->id = $session->attach($this);
        $this->counts = [];

        try{
            $this->player->dataPacket(ClientboundDataStorePacket::create($this->createDataStoreChanges()));
            $this->player->dataPacket(ClientboundDataDrivenUIShowScreenPacket::create("minecraft:custom_form", $this->id, $this->id));

            foreach($this->fields as $path => $value){
                if($value instanceof Observable){
                    $this->unsubscribers[] = $value->subscribe(function($new) use ($path) : void{
						$this->sendUpdate($path, $new);
					});
                }
            }
        }catch(\Throwable $e){
            $session->detach($this);
            $this->release();
            throw $e;
        }
    }

    private function sendUpdate(string $path, mixed $value) : void{
        if($this->id === null || $this->receivingPath === $path){
			return;
		}

        $data = match(true){
            is_bool($value) => new BoolDataStoreValue($value),
            is_string($value) => new StringDataStoreValue($value),
            is_int($value), is_float($value) => new DoubleDataStoreValue((float) $value),
            default => throw new \InvalidArgumentException("DDUI updates must be scalar")
        };

        $this->counts[$path] = ($this->counts[$path] ?? 0) + 1;

        $this->player->dataPacket(ClientboundDataStorePacket::create([new DataStoreUpdate($this->storeName($path), $data, $this->counts[$path])]));
    }

    public function handleUpdate(DataStoreUpdate $update) : void{
        if($this->id === null){
			return;
		}

        $path = $this->pathFromStoreName($update->getName());
        if($path === null){
            return;
        }

        $input = $this->inputs[$path] ?? null; $button = $this->buttons[$path] ?? null;

        if($input === null && $button === null){
			return;
		}

        $control = ($input ?? $button)[0];

        if(!self::value($this->fields[$control . ".visible"]) || self::value($this->fields[$control . ".disabled"])){
			return;
		}

        $counter = $update->getUpdateCount();

        if($counter <= ($this->counts[$path] ?? 0)){
			return;
		}

        $data = $update->getData();

        if(!$data instanceof BoolDataStoreValue && !$data instanceof StringDataStoreValue && !$data instanceof DoubleDataStoreValue){
			return;
		}

        $v = $data->getValue();

        if($input !== null){
            if(!$input[2]($v)){
				return;
			}

            $this->counts[$path] = $counter;
            $this->receivingPath = $path;

            try{
				$input[1]->setData($v);
			}finally{
				$this->receivingPath = null;
			}
        }elseif(is_float($v) && is_finite($v) && $v > (float) self::value($this->fields[$path]) && floor($v) === $v){
            $this->counts[$path] = $counter;
            $this->fields[$path] = $v;
            $button[1]($this->player);
        }
    }

    public function close() : void{
        if($this->id === null){
			return;
		}

        $id = $this->id;

        $this->player->getDDUISession()->detach($this);

        $property = $this->property();

        $this->release();
        $this->player->dataPacket(ClientboundDataDrivenUICloseScreenPacket::create($id));
        $this->clearProperty($property);

        if($this->closed !== null){
			($this->closed)($this->player, "server_closed");
		}
    }

    public function finish(string $reason) : void{
        $property = $this->property();

        $this->release();
        $this->clearProperty($property);

        if($this->closed !== null){
			($this->closed)($this->player, $reason);
		}
    }

    private function clearProperty(string $property) : void{
        $removals = [new DataStoreRemoval("minecraft:" . $property)];

        foreach(array_keys($this->fields) as $path){
            $removals[] = new DataStoreRemoval(self::storeNameForProperty($property, $path));
        }

        $this->player->dataPacket(ClientboundDataStorePacket::create($removals));
    }

    public function release() : void{
        $this->id = null;

        foreach($this->unsubscribers as $unsubscribe){
			$unsubscribe();
		}

        $this->unsubscribers = [];
    }
}