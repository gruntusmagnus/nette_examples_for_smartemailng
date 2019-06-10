<?php

namespace App\ClientModule\Model;

use Orakulum\Model\EntityException;

/**
 * Description of UserException
 *
 * @author JG
 */
class ClientException extends EntityException{

	public static $messages = array(
		'invalid-dic' => array(
            'cs' => 'Špatně zadané DIČ',
            'en' => 'Invalid DIC'
        )
	);
	
}

