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

namespace pocketmine\network\mcpe\protocol;

use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\mcpe\protocol\types\EducationSettingsAgentCapabilities;
use pocketmine\network\mcpe\protocol\types\EducationSettingsExternalLinkSettings;

class EducationSettingsPacket extends DataPacket implements ClientboundPacket{
	public const NETWORK_ID = ProtocolInfo::EDUCATION_SETTINGS_PACKET;

	private string $codeBuilderDefaultUri;
	private string $codeBuilderTitle;
	private bool $canResizeCodeBuilder;
	private bool $disableLegacyTitleBar;
	private string $postProcessFilter;
	private string $screenshotBorderResourcePath;
	private ?EducationSettingsAgentCapabilities $agentCapabilities;
	private ?string $codeBuilderOverrideUri;
	private bool $hasQuiz;
	private ?EducationSettingsExternalLinkSettings $linkSettings;

	/**
	 * @generate-create-func
	 */
	public static function create(
		string $codeBuilderDefaultUri,
		string $codeBuilderTitle,
		bool $canResizeCodeBuilder,
		bool $disableLegacyTitleBar,
		string $postProcessFilter,
		string $screenshotBorderResourcePath,
		?EducationSettingsAgentCapabilities $agentCapabilities,
		?string $codeBuilderOverrideUri,
		bool $hasQuiz,
		?EducationSettingsExternalLinkSettings $linkSettings,
	) : self{
		$result = new self;
		$result->codeBuilderDefaultUri = $codeBuilderDefaultUri;
		$result->codeBuilderTitle = $codeBuilderTitle;
		$result->canResizeCodeBuilder = $canResizeCodeBuilder;
		$result->disableLegacyTitleBar = $disableLegacyTitleBar;
		$result->postProcessFilter = $postProcessFilter;
		$result->screenshotBorderResourcePath = $screenshotBorderResourcePath;
		$result->agentCapabilities = $agentCapabilities;
		$result->codeBuilderOverrideUri = $codeBuilderOverrideUri;
		$result->hasQuiz = $hasQuiz;
		$result->linkSettings = $linkSettings;
		return $result;
	}

	public function getCodeBuilderDefaultUri() : string{
		return $this->codeBuilderDefaultUri;
	}

	public function getCodeBuilderTitle() : string{
		return $this->codeBuilderTitle;
	}

	public function canResizeCodeBuilder() : bool{
		return $this->canResizeCodeBuilder;
	}

	public function disableLegacyTitleBar() : bool{ return $this->disableLegacyTitleBar; }

	public function getPostProcessFilter() : string{ return $this->postProcessFilter; }

	public function getScreenshotBorderResourcePath() : string{ return $this->screenshotBorderResourcePath; }

	public function getAgentCapabilities() : ?EducationSettingsAgentCapabilities{ return $this->agentCapabilities; }

	public function getCodeBuilderOverrideUri() : ?string{
		return $this->codeBuilderOverrideUri;
	}

	public function getHasQuiz() : bool{
		return $this->hasQuiz;
	}

	public function getLinkSettings() : ?EducationSettingsExternalLinkSettings{ return $this->linkSettings; }

	protected function decodePayload(PacketSerializer $in) : void{
		$this->codeBuilderDefaultUri = $in->getString();
		$this->codeBuilderTitle = $in->getString();
		$this->canResizeCodeBuilder = $in->getBool();
		$this->disableLegacyTitleBar = $in->getBool();
		$this->postProcessFilter = $in->getString();
		$this->screenshotBorderResourcePath = $in->getString();
		$this->agentCapabilities = $in->getBool() ? EducationSettingsAgentCapabilities::read($in) : null;
		if($in->getBool()){
			$this->codeBuilderOverrideUri = $in->getString();
		}else{
			$this->codeBuilderOverrideUri = null;
		}
		$this->hasQuiz = $in->getBool();
		$this->linkSettings = $in->getBool() ? EducationSettingsExternalLinkSettings::read($in) : null;
	}

	protected function encodePayload(PacketSerializer $out) : void{
		$out->putString($this->codeBuilderDefaultUri);
		$out->putString($this->codeBuilderTitle);
		$out->putBool($this->canResizeCodeBuilder);
		$out->putBool($this->disableLegacyTitleBar);
		$out->putString($this->postProcessFilter);
		$out->putString($this->screenshotBorderResourcePath);
		if($this->agentCapabilities !== null){
			$out->putBool(true);
			$this->agentCapabilities->write($out);
		}else{
			$out->putBool(false);
		}
		$out->putBool($this->codeBuilderOverrideUri !== null);
		if($this->codeBuilderOverrideUri !== null){
			$out->putString($this->codeBuilderOverrideUri);
		}
		$out->putBool($this->hasQuiz);
		if($this->linkSettings !== null){
			$out->putBool(true);
			$this->linkSettings->write($out);
		}else{
			$out->putBool(false);
		}
	}

	public function handle(PacketHandlerInterface $handler) : bool{
		return $handler->handleEducationSettings($this);
	}
}
