<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\block;

use pocketmine\block\inventory\AnvilInventory;
use pocketmine\block\utils\BlockDataSerializer;
use pocketmine\block\utils\Fallable;
use pocketmine\block\utils\FallableTrait;
use pocketmine\block\utils\HorizontalFacingTrait;
use pocketmine\block\utils\SupportType;
use pocketmine\item\Item;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use pocketmine\world\sound\AnvilBreakSound;
use function assert;
use function mt_rand;

class Anvil extends Transparent implements Fallable{
	use FallableTrait;
	use HorizontalFacingTrait;

	public const UNDAMAGED = 0;
	public const SLIGHTLY_DAMAGED = 1;
	public const VERY_DAMAGED = 2;

	/** The percentage chance that the block will be damaged after use */
	public const DAMAGE_CHANCE = 12;

	private int $damage = self::UNDAMAGED;

	protected function writeStateToMeta() : int{
		return BlockDataSerializer::writeLegacyHorizontalFacing($this->facing) | ($this->damage << 2);
	}

	public function readStateFromData(int $id, int $stateMeta) : void{
		$this->facing = BlockDataSerializer::readLegacyHorizontalFacing($stateMeta & 0x3);
		$this->damage = BlockDataSerializer::readBoundedInt("damage", $stateMeta >> 2, self::UNDAMAGED, self::VERY_DAMAGED);
	}

	public function getStateBitmask() : int{
		return 0b1111;
	}

	protected function writeStateToItemMeta() : int{
		return $this->damage << 2;
	}

	public function getDamage() : int{ return $this->damage; }

	/** @return $this */
	public function setDamage(int $damage) : self{
		if($damage < self::UNDAMAGED || $damage > self::VERY_DAMAGED){
			throw new \InvalidArgumentException("Damage must be in range " . self::UNDAMAGED . " ... " . self::VERY_DAMAGED);
		}
		$this->damage = $damage;
		return $this;
	}

	/**
	 * @return AxisAlignedBB[]
	 */
	protected function recalculateCollisionBoxes() : array{
		return [AxisAlignedBB::one()->squash(Facing::axis(Facing::rotateY($this->facing, false)), 1 / 8)];
	}

	public function getSupportType(int $facing) : SupportType{
		return SupportType::NONE();
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		if($player instanceof Player){
			$player->setCurrentWindow(new AnvilInventory($this->position));
		}

		return true;
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		if($player !== null){
			$this->facing = Facing::rotateY($player->getHorizontalFacing(), true);
		}
		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	public function tickFalling() : ?Block{
		return null;
	}

	public function attemptDamage() : void{
		$world = $this->position->world;
		assert($world !== null);
		if(!$world->getBlock($this->position)->isSameType(VanillaBlocks::ANVIL())){
			return;
		}
		if(mt_rand(0, 100) > self::DAMAGE_CHANCE){
			return;
		}
		$damage = $this->getDamage();
		if(++$damage > self::VERY_DAMAGED){
			$world->useBreakOn($this->position, createParticles: true);
			$world->addSound($this->position, new AnvilBreakSound());
			return;
		}
		$this->setDamage($damage);
	}

	// todo: more fall-related stuff
}
