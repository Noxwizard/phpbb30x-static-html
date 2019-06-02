/**
 * SyntaxHighlighter
 * http://alexgorbatchev.com/
 *
 * SyntaxHighlighter is donationware. If you are using it, please donate.
 * http://alexgorbatchev.com/wiki/SyntaxHighlighter:Donate
 *
 * @version
 * 2.0.320 (May 03 2009)
 * 
 * @copyright
 * Copyright (C) 2004-2009 Alex Gorbatchev.
 *
 * @license
 * This file is part of SyntaxHighlighter.
 * 
 * SyntaxHighlighter is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * SyntaxHighlighter is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with SyntaxHighlighter.  If not, see <http://www.gnu.org/copyleft/lesser.html>.
 */
SyntaxHighlighter.brushes.Tribes = function()
{
	var funcs	=	'onFire onMount newObject addCMCommand addPlayTeamChat ' +
					'setPlayChatMenu contextIssueCommand addCommand setCommanderChatMenu addCommandResponse '+
					'echo remoteEval getItemType getSimTime throwItem '+
					'deployItem schedule nextWeapon prevWeapon CStatus '+
					'getTeam getName centerPrint setValue kill '+
					'setTeam say getClientByName EndFrame playAnimWav playVoice '+
					'sendMessage findSubStr onUse onDeploy decItemCount getLOSInfo '+
					'getObjectType getDataName setPosition setRotation '+
					'setMapName playSound getMountedItem setActive Initialize '+
					'onAdd onDisabled onDestroyed onPower onEnabled OnActivate '+
					'floor ceil startFadeIn startFadeOut add getword getDistance '+
					'containerBoxFillSet objectCount getMapName deleteObject exportObjectToScript focusClient '+
					'focusServer getOwnedObject getFirst getNext onCollision onDamage onEnter onLeave isAIControlled '+
					'getOwnerClient getControlClient itemsToResupply strcat messageAllExcept messageToPlayergetintegertime '+
					'playerSpawn nameToId onInit onRemove onFirst onLast onWaypoint onBlocker onNewPath onKilled '+
					'onDrop onUnmount onNoAmmo onDeactivate onEndSequence onNone onContact setDataFinished limitCommandBandwidth '+
					'setMenuScoreVis getControlObject setOwnedObject setControlObject setSkin getTransportAddress setItemShopping '+
					'clearItemShopping isItemShoppingOn setItemBuying clearItemBuying isItemBuyingOn setInitialTeam '+
					'setClientScoreHeading setGuiMode getGuiMode ExitLobbyMode getVoiceBase getSkinBase getGender getMouseSensitivity '+
					'getMouseXAxisFlip getMouseYAxisFlip useItem getItemCount getManagerId IssueCommand IssueCommandI IssueTargCommand SetCommandStatus '+
					'generatePower isPowerGenerator getPowerCount isPowered getPosition getRotation getTransform isAtRest testPosition '+
					'setSequenceDirection playSequence pauseSequence setRechargeRate getRechargeRate setAutoRepairRate getAutoRepairRate '+
					'repairDamage throw setEnergy getEnergy getDamageLevel setDamageLevel getDamageState applyDamage activateShield '+
					'virtual isActive getMuzzleTransform applyRadiusDamage getRadius getDisabledDamage setIsTarget '+
					'iterateRecursive getObject removeFromSet addToSet getGroup activateGroup nameToID getItemData setVelocity '+
					'getVelocity getCount isRotating hide dot sub neg getFromRot normalize sqrt pow WaypointToWorld '+
					'RenderCanvas rebuildCommandMap spawnProjectile isIn8BitMode issue8BitWarning issueInternetWarning '+
					'postAction focus unfocus moveToWaypoint moveForward moveBackward stop getState getWaypointCount setWaypoint '+
					'addGameServer resolveMasters getResolvedMaster StartGhosting ResetPlayerManager ResetGhostManagers newServer deleteServer '+
					'preloadServerDataBlocks purgeResources isFile which getBoxCenter getObjectByTargetIndex loadObject storeObject '+
					'deleteObject isObject listObjects getClient setDetectParameters setAnimation getArmor incItemCount decItemCount '+
					'setItemCount getItemClassCount mountItem unmountItem getNextMountedItem dropItem setMountObject getMountObject trigger '+
					'isTriggered setSensorSupression getSensorSupression isDead applyImpulse getDamageFlash setDamageFlash setArmor isExposed isJetting lastJetTime '+
					'blowUp getLastContactCount isCrouching ListPlayers SAD SADSetPassword ADSetTimeLimit ADSetTeamInfo CenterPrintAll TopPrintAll '+
					'BottomPrintAll Kick loadMission nextMission addAbsolute remove export clear build addMission initNextMission sprintf escapeString '+
					'RemotePlayAnim setScore setTeamScoreHeading setObjective clearObjectives canMount nextPassengerPoint incPassengers decPassengers getMountPoint';

	var keywords =	'ItemImageData ItemData MineData PlayerData TurretData DebrisData ExplosionData ' +
					'BulletData RocketData GrenadeData MineData SeekingMissileData LaserData LightningData RepairEffectData else ' +
					'elseif if return true false endswitch endwhile ' +
					'extends for foreach function include include_once global if ' +
					'new old_function return static switch use require require_once ' +
					'var while abstract interface public implements extends private protected throw';
	
	var constants	= 'Player:: Client:: Game:: String:: Control:: GameBase:: Vector:: Group:: Projectile:: GUI:: Moveable:: DNET:: ' +
					  'Net:: Server:: BanList:: MissionList:: Team:: Vechile:: @';

	this.regexList = [
		{ regex: SyntaxHighlighter.regexLib.singleLineCComments,	css: 'comments' },			// one line comments
		{ regex: SyntaxHighlighter.regexLib.multiLineCComments,		css: 'comments' },			// multiline comments
		{ regex: SyntaxHighlighter.regexLib.doubleQuotedString,		css: 'string' },			// double quoted strings
		{ regex: SyntaxHighlighter.regexLib.singleQuotedString,		css: 'string' },			// single quoted strings
		{ regex: /[$%]\w+/g,										css: 'variable' },			// variables
		{ regex: new RegExp(this.getKeywords(funcs), 'gmi'),		css: 'functions' },			// common functions
		{ regex: new RegExp(this.getKeywords(constants), 'gmi'),	css: 'constants' },			// constants
		{ regex: new RegExp(this.getKeywords(keywords), 'gm'),		css: 'keyword' }			// keyword
		];
};

SyntaxHighlighter.brushes.Tribes.prototype	= new SyntaxHighlighter.Highlighter();
SyntaxHighlighter.brushes.Tribes.aliases	= ['tribes'];
