<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__.'/../vendor/autoload.php';
require __DIR__ . '/../src/legacyCode.php';


class TechnicalTest extends PHPUnit_Framework_TestCase
{
	protected $db;

	// ** Entity manager object
	protected $em;

	// ** Session object
	protected $session;
	
	public function setUp ()
	{
		$this->db = $this->getMockBuilder('DB')
				         ->setMethods(['insert','delete','update','get'])
				         ->getMock();
		
		$this->session = $this->getMockBuilder('Session')
				         ->setMethods(['get', 'set'])
				         ->getMock();
		
		$this->session->expects($this->any())->method('get')->with('UID')->willReturn('1');
		
		
		//$this->db->expects($this->any())->method('get')->with('user', array('username'=>'daniel'))->willReturn(array('username'=>'daniel', 'password'=>'greg310'));
		
		$this->em = new EntityManager($this->db);
	}
	
	// ** Logging tests
	/**
	 * @group Log
	 *
	 * @covers Log::log
	 */
	public function testLogUserUpdate()
	{
		// ** Set up Database mockup
		$username = 'john';
		$oldPassword = 'pass111';
		$newPassword = 'N3wp999';
		
		$oPasswordOld = EncryptedPasswordVO::EncryptFromRaw($oldPassword);
		$oPasswordNew = EncryptedPasswordVO::EncryptFromRaw($newPassword);
		
		$oldRow = array(
				'username'=>$username,
				'password'=>$oPasswordOld->getValue()
		);
		$newRow = array(
				'username'=>$username,
				'password'=>$oPasswordNew->getValue()
		);
		
		$this->db->expects($this->any())->method('get')
		->with('user', array('username'=>$username))
		->willReturn($oldRow);
		
		$this->db->expects($this->any())->method('get')
		->with('user', array('username'=>$username))
		->willReturn($oldRow);
		
		
		// ** Might want to log to a different database all together so dependancy inject database again into log
		$oLog = new Log($this->db, $this->session);
		$this->em->setLog($oLog);
		
		// ** Load and change password
		$oUser = $this->em->load('User', 'john');
		$oUser->setPassword($oPasswordNew);
		
		// ** Expect the log object to insert the record into table `Log` when we persist a Model object
		$entity = $oUser->getEntity();
		$serialized = serialize($oUser->exportAsArray());
		$action = 'UPDATE';
		
		$this->db->expects($this->once())
		->method('update')
		->with('user', $newRow, array('username'=>$username))
		->willReturn(true);
		
		$this->db->expects($this->exactly(1))->method('insert')->with(
			'Log',
			array(
				$this->session->get('UID'),
				$entity,
				$serialized,
				$action
			)
		)->willReturn(true);
		
		// ** Persist to db
		$this->em->persist($oUser);
		
	}
	
	/**
	 * @group Log
	 * @group current
	 *
	 * @covers Log::log
	 */
	public function testLogUserInsert()
	{
		// ** Set up Database mockup
		$username = 'john';
		$password = 'pass111';
		
		$oPassword = EncryptedPasswordVO::EncryptFromRaw($password);
		
		$row = array(
				'username'=>$username,
				'password'=>$oPassword->getValue()
		);
		
		// ** Might want to log to a different database all together so dependancy inject database again into log
		$oLog = new Log($this->db, $this->session);
		$this->em->setLog($oLog);
		
		// ** Load and change password
		$oUser = $this->em->load('User');
		$oUser->setUsername($username);
		$oUser->setPassword($oPassword);
		
		// ** Expect the log object to insert the record into table `Log` when we persist a Model object
		$entity = $oUser->getEntity();
		$serialized = serialize($oUser->exportAsArray());
		$action = 'INSERT';
		
		// ** Expect DB to execute insert exactly twice, once for the user record, once the log...
		$this->db->expects($this->exactly(2))
		->method('insert')
		->withConsecutive(
						array('user', $row),
						array(
								'Log',
								array(
										$this->session->get('UID'),
										$entity,
										$serialized,
										$action
								)
						)
				
		)->willReturnOnConsecutiveCalls(
				array(
						true,
						true
				));
		
		// ** Persist to db
		$this->em->persist($oUser);
		
	}
	
	// ** PasswordVO tests
	/**
	 * @group PasswordVO
	 *
	 * @covers PasswordVO::equals
	 */
	public function testRawPasswordVOEncrypt()
	{
		$oP1 = new RawPasswordVO('password1');
		$oP2 = $oP1->encrypt();
		$this->assertTrue($oP1->equals($oP2)); // return true
	}
	/**
	 * @group PasswordVO
	 *
	 * @covers PasswordVO::equals
	 */
	public function testRawPasswordVONoEncrypt()
	{
		$oP1 = new RawPasswordVO('password1');
		$oP2 = new RawPasswordVO('password1');
		$this->assertTrue($oP1->equals($oP2)); // return true
	}
	/**
	 * @group PasswordVO
	 *
	 * @covers RawPasswordVO::encrypt
	 * @covers PasswordVO::equals
	 *
	 * @expectedException     PasswordVOCannotCompareException
	 * @expectedExceptionCode 0
	 */
	public function testEncryptedPasswordVOEquivalence()
	{
		$oP1 = new RawPasswordVO('password1');
		$oP3 = $oP1->encrypt();
		$oP2 = new RawPasswordVO('password0');
		$oP4 = $oP2->encrypt();
		$this->assertTrue($oP3->equals($oP4)); // throw an exception
	}
	
	public function testCreateUser ()
	{
		$username = 'john';
		$password = 'pass123';

		$oPassword = EncryptedPasswordVO::EncryptFromRaw($password);
		
		$row = array(
				'username'=>$username,
				'password'=>$oPassword->getValue()
		);
		
		$oUser = $this->em->load('User');
		$oUser->setUsername($username);
		$oUser->setPassword($oPassword);
		
		$this->db->expects($this->once())
					->method('get')
					->with('user', array('username'=>$username))
					->willReturn(false);
		$this->db->expects($this->once())
					->method('insert')
					->with('user', $row)
					->willReturn(true);
		
		$this->assertTrue($this->em->persist($oUser));
		
	}

	/**
	 * @group password
	 * 
	 * @covers EntityManager::load
	 * @covers EntityManager::persist
	 * 
	 * @expectedException     ModelValidationException
	 * @expectedExceptionCode 20
	 */
	public function testCreateUserWithShortPassword ()
	{
		// If password is shorter than 6 chars, the user should not be created

		$username = 'johny';
		$password = 'abc12';
		$oPassword = EncryptedPasswordVO::EncryptFromRaw($password);
		
		$row = array(
				'username'=>$username,
				'password'=>$oPassword->getValue()
		);
		
		$oUser = $this->em->load('User');
		$oUser->setUsername($username);
		$oUser->setPassword($oPassword);
		
		$this->db->expects($this->never())
				->method('insert')
				->with('user', $row);
				
		// Should trigger ModelValidationException
		$this->em->persist($oUser);
		
	}

	/**
	 * @group password
	 * @group failing
	 * 
	 * @covers EntityManager::load
	 * @covers EntityManager::persist
	 */
	public function testChangePassword ()
	{
		$username = 'john';
		$oldPassword = 'pass111';
		$newPassword = 'pass123';
		
		$oPasswordOld = EncryptedPasswordVO::EncryptFromRaw($oldPassword);
		$oPasswordNew = EncryptedPasswordVO::EncryptFromRaw($newPassword);
		
		$oldRow = array(
			'username'=>$username,
			'password'=>$oPasswordOld->getValue()
		);
		$newRow = array(
			'username'=>$username,
			'password'=>$oPasswordNew->getValue()
		);
		
		$this->db->expects($this->any())
				->method('get')
				->with('user', array('username'=>$username))
				->willReturn($oldRow);
				
		$oUser = $this->em->load('User', $username);
		
		$oUser->setPassword($oPasswordNew);
		
		$this->db->expects($this->once())
				->method('update')
				->with('user', $newRow, array('username'=>$username))
				->willReturn(true);
				
		$this->assertTrue($this->em->persist($oUser));
		
	}

	/**
	 * @group password
	 * 
	 * @expectedException     ModelValidationException
	 * @expectedExceptionCode 20
	 */
	public function testChangePasswordWithShortPassword ()
	{
		// If the new password is shorter than 6 chars, it shouldn't be updated
		$username = 'john';
		$oldPassword = 'pass111';
		$newPassword = 'N3wp9';
		
		$oPasswordOld = EncryptedPasswordVO::EncryptFromRaw($oldPassword);
		$oPasswordNew = EncryptedPasswordVO::EncryptFromRaw($newPassword);
		
		$oldRow = array(
				'username'=>$username,
				'password'=>$oPasswordOld->getValue()
		);
		$newRow = array(
				'username'=>$username,
				'password'=>$oPasswordNew->getValue()
		);
		
		$this->db->expects($this->any())->method('get')
				->with('user', array('username'=>$username))
				->willReturn($oldRow);
		
		$oUser = $this->em->load('User', $username);
		
		$oUser->setPassword($oPasswordNew);
		
		$this->db->expects($this->never())->method('update')->with('user', $newRow, array('username'=>$username));
		
		// Calling the persist() function will throw ModelValidationException
		$this->em->persist($oUser);
	}

	/**
	 * @group password
	 *
	 * @covers EntityManager::load
	 * @covers EntityManager::persist
	 */
	public function testChangePasswordOfNonExistingUser()
	{
		// If user doesn't exist, the password should not be changed
		
		$username = 'paul';
		$oldPassword = 'O1dpass!9';
		$newPassword = 'N3wpass!9';
		
		$oPasswordOld = new RawPasswordVO($oldPassword);
		$oPasswordOld = $oPasswordOld->encrypt();
		$oPasswordNew = new RawPasswordVO($newPassword);
		$oPasswordNew = $oPasswordNew->encrypt();
		
		
		$oldRow = array(
			'username'=>$username,
			'password'=>$oPasswordOld->getValue()
		);
		$newRow = array(
				'username'=>$username,
				'password'=>$oPasswordNew->getValue()
		);
		
		$this->db->expects($this->exactly(2))
			->method('get')
			->with('user', array('username'=>$username))
			->willReturn(false);
		
		$oUser = $this->em->load('User', $username);
		
		$this->assertEquals($oUser, false);
		
		if ($oUser === false)
		{
			$oUser = $this->em->load('User');
			$oUser->setUsername($username);
			$oUser->setPassword($oPasswordNew);
		}
		
		$this->db->expects($this->never())->method('update')->willReturn(true);
		$this->db->expects($this->once())->method('insert')->with('user', $newRow)->willReturn(true);
		
		$isOK = $this->em->persist($oUser);
		
	}

	/**
	 * @group user
	 *
	 * @covers EntityManager::load
	 * @covers EntityManager::delete
	 */
	public function testDeleteUser ()
	{
		$username = 'john';
		$password = 'pass123';
		
		$row = array(
				'username'=>$username,
				'password'=>$password
		);
		
		$this->db->expects($this->exactly(2))
			->method('get')
			->with('user', array('username'=>$username))
			->willReturn($row);

		$this->db->expects($this->once())
			->method('delete')
			->with('user', array('username'=>$username))
			->willReturn(true);
		
		$oUser = $this->em->load('User', $username);
			
		$this->em->delete($oUser);
	}

	/**
	 * @group user
	 *
	 * @covers EntityManager::load
	 * @covers EntityManager::delete
	 */
	public function testDeleteNonExistingUser ()
	{
		// If user doesn't exist, we shouldn't try to delete it

		$username = 'james';
		$password = 'pass123';
		
		$this->db->expects($this->exactly(2))
				->method('get')
				->with('user', array('username'=>$username))
				->willReturn(false);
		
		$this->db->expects($this->never())
				->method('delete')
				->with('user', array('username'=>$username))
				->willReturn(false);
		
		$oUser = $this->em->load('User', $username);
		
		if ($oUser === false)
		{
			$oUser = $this->em->load('User');
			$oUser->setUsername($username);
			$oUser->setPassword(EncryptedPasswordVO::EncryptFromRaw($password));
		}
			
		$this->assertFalse($this->em->delete($oUser));
	}

	/**
	 * @group user
	 *
	 * @covers EntityManager::load
	 */
	public function testUserDoesntExists ()
	{
		// If we get no data about the user, assume it doesn't exist

		$username = 'johnny';

		$this->db->expects($this->once())
				->method('get')
				->with('user', array('username'=>$username))
				->willReturn(false);
		
		$oUser = $this->em->load('User', $username);
		
		$this->assertFalse($oUser);
	}
}

