<?php

/*
function NewUser ($db, $username, $password)
{
	if (strlen($password) < 6) return false;
	$db->insert ($username, md5($password));
}

function DeleteUser ($db, $username)
{
	if (!UserExists($db, $username)) return false;
	$db->delete ($username);
	
}

function ChangePassword ($db, $username, $password)
{
	if (strlen($password) < 6) return false;
	if (!UserExists($db, $username)) return false;
	$db->update ($username, md5($password));
}

function UserExists ($db, $username)
{
	$user = $db->get($username);
	return !empty($user);
}
*/

/*
interface DatabaseInterface
{
	function insert($entity, $row=array());
	function update($entity, $row=array(), $criteria=array());
	function delete($entity, $criteria=array());
	function get($entity, $criteria=array());
}
*/

class Log
{
	protected $session;
	protected $db;
	
	function __construct(DB $database, Session $session)
	{
		$this->db = $database;
		$this->session = $session;
	}
	
	function log(Model $model, $action)
	{
		$uid = $this->session->get('UID');
		
		// ** Database table will add timestamp automatically on INSERT
		$row = array(
			$uid,
			$model->getEntity(),
			serialize($model->exportAsArray()),
			$action
		);
		
		return $this->db->insert('Log', $row);
	}
}

/**
 * Exception thrown when you try to compare two encrypted passwords - impossible! (made for extra points)
 * @author iantr
 *
 */
class PasswordVOCannotCompareException extends Exception
{
	public function __construct($message, $code = 0, Exception $previous = null) {
		
		// make sure everything is assigned properly
		parent::__construct($message, $code, $previous);
	}

	// custom string representation of object
	public function __toString() {
		return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
	}
}

/**
 * A password value object base class (made for extra points)
 * @author iantr
 *
 */
abstract class PasswordVO
{
	protected $_value = '';
	
	function __construct($value)
	{
		$this->_value = $value;
	}
	
	static function IsEqual(PasswordVO $p1, PasswordVO $p2)
	{
		$classTypeP1 = get_class($p1);
		$classTypeP2 = get_class($p2);
		
		if ($classTypeP1 === 'RawPasswordVO' &&
				$classTypeP2 === 'RawPasswordVO') 
		{
			return $p1->getValue() === $p2->getValue();
		}
		else if ($classTypeP1 === 'EncryptedPasswordVO' &&
				$classTypeP2 === 'RawPasswordVO')
		{
			return crypt($p2->getValue(), $p1->getValue()) === $p1->getValue();
		}
		else if ($classTypeP1 === 'RawPasswordVO' &&
				$classTypeP2 === 'EncryptedPasswordVO')
		{
			return crypt($p1->getValue(), $p2->getValue()) === $p2->getValue();
		}
		else if ($classTypeP1 === 'EncryptedPasswordVO' &&
				$classTypeP2 === 'EncryptedPasswordVO')
		{
			throw new PasswordVOCannotCompareException('Cannot compare two encrypted passwords for equivalance. Algo is one-way hash.');
		}
		return false;
	}
	
	function getValue()
	{
		return $this->_value;
	}
	
	function equals(PasswordVO $oPassword)
	{
		return PasswordVO::IsEqual($this, $oPassword);
	}
}

/**
 * Password Value Object (made for bonus points)
 * @author iantr
 *
 */
class RawPasswordVO extends PasswordVO
{
	/*
	 * Encrypts a raw password
	 * @return EncryptedPasswordVO
	 */
	function encrypt($salt='usesomadasdsadsadsadasdasdasdsadesillystringfors', $iterations=7)
	{
		return EncryptedPasswordVO::EncryptFromRaw($this->_value, $salt, $iterations);
	}
	
}

/**
 * Encrypted Password Value Object (made for bonus points)
 * @author iantr
 *
 */
class EncryptedPasswordVO extends PasswordVO
{
	protected $_raw=false;
	
	function __construct($value, $raw=false)
	{
		parent::__construct($value);
		$this->_raw = $raw;
	}
	
	function getRaw()
	{
		return $this->_raw;
	}
		
	/**
	 * Encrypts the password using blowfish algorithm
	 * @param string $salt
	 * @param number $iterations
	 * @return EncryptedPasswordVO
	 */
	static function EncryptFromRaw($value, $salt='usesomadasdsadsadsadasdasdasdsadesillystringfors', $iterations=7)
	{
		$salt = sprintf('$2a$%02d$%s', $iterations, $salt);
		$encrypted = crypt($value, $salt);
		return new EncryptedPasswordVO($encrypted, $value);
	}
}

/**
 * Example usage
*/
/*
$oP1 = new RawPasswordVO('password1');
$oP2 = $oP1->encrypt();
var_dump($oP1->equals($oP2)); // return true
*/
/*
$oP1 = new RawPasswordVO('password1');
$oP2 = new RawPasswordVO('password1');
var_dump($oP1->equals($oP2)); // return true
*/
/*
$oP1 = new RawPasswordVO('password1');
$oP2 = new RawPasswordVO('password0');
var_dump($oP1->equals($oP2)); // return false
*/
/*
$oP1 = new RawPasswordVO('password1');
$oP3 = $oP1->encrypt();
$oP2 = new RawPasswordVO('password0');
$oP4 = $oP2->encrypt();
var_dump($oP3->equals($oP4)); // throw an exception
*/

/**
 * 
 * @author iantr
 *
 */
class EntityManager
{
	protected $db;
	protected $_log = null;
	
	/**
	 * 
	 * @param unknown $db Dependancy inject database object into EntityManager. 
	 */
	function __construct($db)
	{
		$this->db = $db;
	}
	
	/**
	 * Assign an optional Log object to log record changes and the user that made the changes
	 * @param Log $log A log object that implements the log function
	 */
	function setLog(Log $log)
	{
		$this->_log = $log;
	}
	
	function _log($isOK, Model $model, $action)
	{
		if ($isOK && !empty($this->_log))
		{
			$this->_log->log($model, $action);
		}
	}
	
	/**
	 * Persists the model to the database
	 * 
	 * @param Model $model
	 * @return true if update/insert a success, false if update/insert failed
	 */
	function persist(Model $model)
	{
		$entity = $model->getEntity();
		
		// ** Check if record exists
		$row = $this->db->get($entity, array($model->getKeyFieldName()=>$model->getKeyValue()));
		
		// ** Validate the model
		$model->validate();
		
		// ** Prepare model row
		$rowToInsert = $model->onBeforeSave($row);
		
		// ** If record exists than update, otherwise insert
		if (is_array($row))
		{
			$isOK = $this->db->update($entity, $rowToInsert, array($model->getKeyFieldName()=>$model->getKeyValue()));
			$action = 'UPDATE';
		}
		else
		{
			$isOK = $this->db->insert($entity, $rowToInsert);
			$action = 'INSERT';
		}
		
		// ** Log the database event if it executed successfully
		$this->_log($isOK, $model, $action);
		
		return $isOK;
	}
	
	/**
	 * Deletes a model if it exists in the database
	 * 
	 * @param Model $model
	 * @return boolean
	 */
	function delete(Model $model)
	{
		$isOK = false;
		
		$entity = $model->getEntity();
		
		// ** Check if record exists
		$row = $this->db->get($entity, array($model->getKeyFieldName()=>$model->getKeyValue()));
		
		if (is_array($row))
		{
			$isOK = $this->db->delete($entity, array($model->getKeyFieldName()=>$model->getKeyValue()));
		}
		
		$this->_log($isOK, $model, 'DELETE');
		return $isOK;
	}
	
	/**
	 * Loads a Model from given criteria
	 * 
	 * @param unknown $modelName
	 * @param string $criteria The key field value to lookup
	 * @param array $criteria Set of field/value pairs to query
	 * @param false $criteria Load a new user
	 * @return Model|boolean Returns a Model object on success. Returns false on failure (if record with criteria doesn't exist in database)
	 */
	function load($modelName, $criteria=false)
	{
		$model = new $modelName();
		
		$entity = $model->getEntity();
		
		if ($criteria === false)
		{
			return $model;
		}
		else if (is_array($criteria))
		{
			$row = $this->db->get($entity, $criteria);
		}
		else 
		{
			$row = $this->db->get($entity, array($model->getKeyFieldName()=>$criteria));
		}
		
		if (!$row) return false;
		
		$row = $model->onLoadData($row);
		
		$model->loadFromArray($row);
		
		// ** Log the user id of the user that looks up this record
		$this->_log(true, $model, 'LOOKUP');
		
		return $model;
	}
}

/**
 * The Model abstract (super) class
 * @author iantr
 *
 */
abstract class Model
{
	protected $_key = 'id', $_entity = '';
	protected $_data = array(), $_row = array();
	
	/**
	 * Returns the name of the key (primary) field
	 * @return string Key field
	 */
	function getKeyFieldName()
	{
		return $this->_key;
	}
	
	/**
	 * Returns the Key field value
	 * @return boolean|mixed Returns false if the key field has not yet been set. Otherwise, returns the key field value
	 */
	function getKeyValue()
	{
		return (isset($this->_data[$this->_key]) ? $this->_data[$this->_key] : false);
	}
	
	/**
	 * Hook called when data is loaded from database into model. Overload to add value objects (i.e. for password)
	 * @param array $row
	 * @return array Manipulated data row array loaded from database
	 */
	function onLoadData($row=array())
	{
		return $row;
	}
	
	/**
	 * Loads data from an array into the class
	 * @param array $array Data to load in key/value pair array format
	 */
	function loadFromArray($array)
	{
		// ** Implement hook for load data event
		$array = $this->onLoadData($array);
		
		// ** Assign manipulated array
		$this->_data = $array;
	}
	
	/**
	 * Exports the data from the Model into an array
	 * @return array The internal field data from the Model in key/value pair array format.
	 */
	function exportAsArray()
	{
		return $this->_data;
	}
	
	/**
	 * Sets an attribute value
	 * @param string $attribute Name of field/attribute
	 * @param mixed $value Value of the attribute
	 */
	function setAttributeValue($attribute, $value)
	{
		$this->_data[$attribute] = $value;
	}
	
	/**
	 * Gets an attribute value
	 * @param string $attribute Name of field/attribute
	 * @return mixed The attribute value
	 */
	function getAttributeValue($attribute)
	{
		return $this->_data[$attribute];
	}
	
	/**
	 * Validates the data stored in the Model
	 * @param array $error Can overload and inject pre-existing errors
	 * @throws ModelValidationException If an error has occurred
	 */
	function validate($error=array())
	{
		
		//var_dump($this->getKeyValue());
		if (empty($this->getKeyValue()))
		{
			$error[$this->getKeyFieldName()] = 'You must provide a '.str_replace('_', ' ', $this->getKeyFieldName());
		}
		
		$numOfErrors = count($error);
		if ($numOfErrors)
		{
			throw new ModelValidationException($error, $numOfErrors, 'Some invalid fields', 20);
		}
	}
	
	
	/**
	 * A function that can be overloaded which prepares the Model data for insert
	 * @return array Row that will be inserted/updated
	 */
	function onBeforeSave($rowFromDB=array())
	{
		$this->_row = $this->_data;
		return $this->_row;
	}
	
	/**
	 * The name of the database table
	 * @return string Database table name the model is associated with
	 */
	function getEntity()
	{
		return $this->_entity;
	}
}

/**
 * User model class derived from the Model abstract class
 * 
 * @author iantr
 *
 */
class User extends Model
{
	protected $_username, $_password;
	
	protected $_key = 'username';
	
	protected $_entity = 'user';
	
	protected $_dirtyPassword = false;
	
	function __contruct($username='', $password='')
	{
		$this->setUsername($username);
		$this->setPassword($password);
	}
	
	/**
	 * Set the username
	 * @param string $username
	 */
	public function setUsername($username)
	{
		$this->setAttributeValue('username', $username);
	}
	
	/**
	 * Get the username
	 * @return string Username
	 */
	public function getUsername() 
	{
		return $this->getAttributeValue('username');
	}
	
	/**
	 * Set the password (must be 6 or more characters long)
	 * @param PasswordVO $password PasswordVO (value object) with password data
	 */
	public function setPassword(PasswordVO $password)
	{
		$this->_dirtyPassword = true;
		$this->setAttributeValue('password', $password);
	}
	
	/**
	 * Get the password
	 * @return string Password
	 */
	public function getPassword()
	{
		return $this->getAttributeValue('password');
	}
	
	/**
	 * Add validation rules here
	 * @param array $error Any pre-existing errors you wish to inject
	 */
	function validate($error=array())
	{
		// ** If password length is less than 6 chars long
		
		$oPassword = $this->getPassword();
		
		if (strlen($oPassword->getRaw()) < 6) 
		{
			$error['password'] = 'Password must be longer than 6 characters';	
		}
		parent::validate($error);
		
	}
	
	/**
	 * Overload default prepare() function to encrypt password before insert/update.
	 * @return string[]|NULL[]
	 */
	function onBeforeSave($rowFromDB=array())
	{
		$row = parent::onBeforeSave($rowFromDB);
		
		// If password is dirty than encrypt
		if ($this->_dirtyPassword) {
			
			$oPassword = null;
			
			// ** Need to encrypt the password
			if (is_string($row['password']))
			{
				$oPassword = EncryptedPasswordVO::EncryptFromRaw($row['password']);
			}
			else if (get_class($row['password']) === 'RawPasswordVO')
			{
				$oPassword = $row['password']->encrypt();
			} 
			else if (get_class($row['password']) === 'EncryptedPasswordVO') 
			{
				$oPassword = $row['password'];
			} 
			else
			{
				$oPassword = EncryptedPasswordVO::EncryptFromRaw($row['test']);
			}
			
			$password = $oPassword->getValue();
				
			$row['password'] = $password;
		}
		return $row;
	}
	
	function onLoadData($row=array())
	{
		$row['password'] = new EncryptedPasswordVO($row['password']);
		return $row;
	}
	
}

/**
 * Exception class thrown when validation errors exist
 * 
 * @author iantr
 *
 */
class ModelValidationException extends Exception
{
	protected $_errors = array();
	
	protected $_numOfErrors;
	
	public function __construct($errors=array(), $numOfErrors=0, $message, $code = 0, Exception $previous = null) {
		$this->_errors = $errors;
		$this->_numOfErrors = $numOfErrors;
		
		// make sure everything is assigned properly
		parent::__construct($message, $code, $previous);
	}
	
	function getValidationErrors()
	{
		return $this->_errors;
	}
	
	function getTotalErrorCount()
	{
		return $this->_numOfErrors;
	}
	
	// custom string representation of object
	public function __toString() {
		return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
	}
}

