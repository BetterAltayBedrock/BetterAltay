<?php

/*
 * This file is part of BedrockProtocol.
 * Copyright (C) 2014-2022 PocketMine Team <https://github.com/pmmp/BedrockProtocol>
 *
 * BedrockProtocol is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol\types;

use pocketmine\network\mcpe\NetworkBinaryStream;

use function count;
use function is_int;
use function is_bool;
use function is_float;
use function is_array;
use function is_string;
use function array_values;

final class DynamicDataStoreValue{
	
    private mixed $value;

    public function __construct(mixed $value){
        $this->value = $value;
    }

    public function getValue() : mixed{ return $this->value; }

    public function write(NetworkBinaryStream $out, int $depth = 0) : void{
        if($depth > 32){
			throw new \InvalidArgumentException("DDUI value is too deeply nested");
		}

        $v = $this->value;

        $type = match(true){
            $v === null => 0,
			is_bool($v) => 1,
			is_int($v) => 2,
            is_float($v) => 3,
			is_string($v) => 4,
			is_array($v) => 5,
            $v instanceof \stdClass => 6,
            default => throw new \InvalidArgumentException("Unsupported DDUI value")
        };

        $out->putLInt($type);

        switch($type){
            case 1:
				$out->putBool($v);
			break;

            case 2:
				$out->putLLong($v);
			break;

            case 3:
				$out->putLDouble($v);
			break;

            case 4:
				$out->putString($v);
			break;

            case 5:
            case 6:
                $entries = (array) $v;

                if($type === 5 && $entries !== array_values($entries)){
                    throw new \InvalidArgumentException("Use stdClass for DDUI maps");
                }

                if(count($entries) > 4096){
					throw new \InvalidArgumentException("DDUI collection too large");
				}

                $out->putUnsignedVarInt(count($entries));

                foreach($entries as $key => $item){
                    if($type === 6){
						$out->putString((string) $key);
					}
                    (new self($item))->write($out, $depth + 1);
                }
                break;
        }
    }

    public static function read(NetworkBinaryStream $in, int $depth = 0) : self{
        if($depth > 32){
			throw new \UnexpectedValueException("DDUI value is too deeply nested");
		}

        $type = $in->getLInt();

        switch($type){
            case 0:
				return new self(null);

            case 1:
				return new self($in->getBool());

            case 2:
				return new self($in->getLLong());

            case 3:
				return new self($in->getLDouble());

            case 4:
				return new self($in->getString());

            case 5:
            case 6:
                $count = $in->getUnsignedVarInt();

                if($count > 4096){
					throw new \UnexpectedValueException("DDUI collection too large");
				}

                $value = $type === 6 ? new \stdClass() : [];

                for($i = 0; $i < $count; ++$i){
                    if($type === 6){
                        $key = $in->getString();
                        $value->{$key} = self::read($in, $depth + 1)->getValue();
                    }else{
						$value[] = self::read($in, $depth + 1)->getValue();
					}
                }
                return new self($value);

            default:
				throw new \UnexpectedValueException("Unknown DDUI dynamic type " . $type);
        }
    }
}