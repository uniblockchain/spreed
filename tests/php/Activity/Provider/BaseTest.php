<?php
/**
 * @copyright Copyright (c) 2017 Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Spreed\Tests\php\Activity\Provider;


use OCA\Spreed\Activity\Provider\Base;
use OCA\Spreed\Manager;
use OCA\Spreed\Room;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use Test\TestCase;

/**
 * Class BaseTest
 *
 * @package OCA\Spreed\Tests\php\Activity
 */
class BaseTest extends TestCase {

	/** @var IFactory|\PHPUnit_Framework_MockObject_MockObject */
	protected $l10nFactory;
	/** @var IURLGenerator|\PHPUnit_Framework_MockObject_MockObject */
	protected $url;
	/** @var IManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $activityManager;
	/** @var IUserManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $userManager;
	/** @var Manager|\PHPUnit_Framework_MockObject_MockObject */
	protected $manager;

	public function setUp() {
		parent::setUp();

		$this->l10nFactory = $this->createMock(IFactory::class);
		$this->url = $this->createMock(IURLGenerator::class);
		$this->activityManager = $this->createMock(IManager::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->manager = $this->createMock(Manager::class);
	}

	/**
	 * @param string[] $methods
	 * @return Base|\PHPUnit_Framework_MockObject_MockObject
	 */
	protected function getProvider(array $methods = []) {
		$methods[] = 'parse';
		return $this->getMockBuilder(Base::class)
			->setConstructorArgs([
				$this->l10nFactory,
				$this->url,
				$this->activityManager,
				$this->userManager,
				$this->manager,
			])
			->setMethods($methods)
			->getMock();
	}


	public function dataPreParse() {
		return [
			[true, 'app-dark.png'],
			[false, 'app-dark.svg'],
		];
	}

	/**
	 * @dataProvider dataPreParse
	 *
	 */
	public function testPreParse($png, $imagePath) {
		/** @var IEvent|\PHPUnit_Framework_MockObject_MockObject $event */
		$event = $this->createMock(IEvent::class);
		$event->expects($this->once())
			->method('getApp')
			->willReturn('spreed');

		$this->activityManager->expects($this->once())
			->method('getRequirePNG')
			->willReturn($png);

		$this->url->expects($this->once())
			->method('imagePath')
			->with('spreed', $imagePath)
			->willReturn('imagePath');
		$this->url->expects($this->once())
			->method('getAbsoluteURL')
			->with('imagePath')
			->willReturn('getAbsoluteURL');

		$event->expects($this->once())
			->method('setIcon')
			->with('getAbsoluteURL')
			->willReturnSelf();

		$provider = $this->getProvider();
		$this->assertSame($event, static::invokePrivate($provider, 'preParse', [$event]));
	}

	/**
	 * @expectedException \InvalidArgumentException
	 */
	public function testPreParseThrows() {
		/** @var IEvent|\PHPUnit_Framework_MockObject_MockObject $event */
		$event = $this->createMock(IEvent::class);
		$event->expects($this->once())
			->method('getApp')
			->willReturn('activity');
		$provider = $this->getProvider();
		static::invokePrivate($provider, 'preParse', [$event]);
	}

	public function dataSetSubject() {
		return [
			['No placeholder', [], 'No placeholder'],
			['This has one {placeholder}', ['placeholder' => ['name' => 'foobar']], 'This has one foobar'],
			['This has {number} {placeholders}', ['number' => ['name' => 'two'], 'placeholders' => ['name' => 'foobars']], 'This has two foobars'],
		];
	}

	/**
	 * @dataProvider dataSetSubject
	 *
	 * @param string $subject
	 * @param array $parameters
	 * @param string $parsedSubject
	 */
	public function testSetSubject($subject, array $parameters, $parsedSubject) {
		$provider = $this->getProvider();

		$event = $this->createMock(IEvent::class);
		$event->expects($this->once())
			->method('setParsedSubject')
			->with($parsedSubject)
			->willReturnSelf();
		$event->expects($this->once())
			->method('setRichSubject')
			->with($subject, $parameters)
			->willReturnSelf();

		self::invokePrivate($provider, 'setSubjects', [$event, $subject, $parameters]);
	}

	public function dataGetRoom() {
		return [
			[Room::ONE_TO_ONE_CALL, 23, 'private-call', 'private-call', 'one2one'],
			[Room::GROUP_CALL, 42, 'group-call', 'group-call', 'group'],
			[Room::PUBLIC_CALL, 128, 'public-call', 'public-call', 'public'],
			[Room::ONE_TO_ONE_CALL, 23, '', 'a call', 'one2one'],
			[Room::GROUP_CALL, 42, '', 'a call', 'group'],
			[Room::PUBLIC_CALL, 128, '', 'a call', 'public'],
		];
	}

	/**
	 * @dataProvider dataGetRoom
	 *
	 * @param int $type
	 * @param int $id
	 * @param string $name
	 * @param string $expectedName
	 * @param string $expectedType
	 */
	public function testGetRoom($type, $id, $name, $expectedName, $expectedType) {
		$provider = $this->getProvider();

		$room = $this->createMock(Room::class);
		$room->expects($this->once())
			->method('getType')
			->willReturn($type);
		$room->expects($this->once())
			->method('getId')
			->willReturn($id);
		$room->expects($this->once())
			->method('getName')
			->willReturn($name);

		$l = $this->createMock(IL10N::class);
		$l->expects($this->any())
			->method('t')
			->willReturnCallback(function($text, $parameters = []) {
				return vsprintf($text, $parameters);
			});

		$this->assertEquals([
			'type' => 'call',
			'id' => $id,
			'name' => $expectedName,
			'call-type' => $expectedType,
		], self::invokePrivate($provider, 'getRoom', [$l, $room]));
	}

	public function dataGetUser() {
		return [
			['test', [], false, 'Test'],
			['foo', ['admin' => 'Admin'], false, 'Bar'],
			['admin', ['admin' => 'Administrator'], true, 'Administrator'],
		];
	}

	/**
	 * @dataProvider dataGetUser
	 *
	 * @param string $uid
	 * @param array $cache
	 * @param bool $cacheHit
	 * @param string $name
	 */
	public function testGetUser($uid, $cache, $cacheHit, $name) {
		$provider = $this->getProvider(['getDisplayName']);

		self::invokePrivate($provider, 'displayNames', [$cache]);

		if (!$cacheHit) {
			$provider->expects($this->once())
				->method('getDisplayName')
				->with($uid)
				->willReturn($name);
		} else {
			$provider->expects($this->never())
				->method('getDisplayName');
		}

		$result = self::invokePrivate($provider, 'getUser', [$uid]);
		$this->assertSame('user', $result['type']);
		$this->assertSame($uid, $result['id']);
		$this->assertSame($name, $result['name']);
	}

	public function dataGetDisplayName() {
		return [
			['test', true, 'Test'],
			['foo', false, 'foo'],
		];
	}

	/**
	 * @dataProvider dataGetDisplayName
	 *
	 * @param string $uid
	 * @param bool $validUser
	 * @param string $name
	 */
	public function testGetDisplayName($uid, $validUser, $name) {
		$provider = $this->getProvider();

		if ($validUser) {
			$user = $this->createMock(IUser::class);
			$user->expects($this->once())
				->method('getDisplayName')
				->willReturn($name);
			$this->userManager->expects($this->once())
				->method('get')
				->with($uid)
				->willReturn($user);
		} else {
			$this->userManager->expects($this->once())
				->method('get')
				->with($uid)
				->willReturn(null);
		}

		$this->assertSame($name, self::invokePrivate($provider, 'getDisplayName', [$uid]));
	}
}
