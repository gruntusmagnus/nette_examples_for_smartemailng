<?php

namespace App\ClientModule\Model;

use Orakulum\Model\AbstractFacade,
	Orakulum\Components\Ares\Ares;

use App\CommonModule\Model\Address,
	App\CommonModule\Model\Country,
	App\CommonModule\Model\Contact,
	App\EmployeeModule\Model\Employee,
    App\EmployeeModule\Model\EmployeeModelException;

/**
 * Description of UserFacade
 *
 * @author JG
 */
class ClientFacade extends AbstractFacade{

	private $allowedClientsCount;
    protected $modelName = 'client';
	
	
	
	
	public function findClients($limit = null, $offset = null, $sorting = array(), $filtering = array(), $ids = array()){
		try{
			$this->beginTransaction();
			$result = $this->getModel('client')->findClients($limit, $offset, $sorting, $filtering, $ids);
			$this->commitTransaction();
			return $result;
		}catch(\Exception $e){
			$this->rollBackTransaction();
			$this->processException($e);
			throw ClientModelException::canNotFindClients($limit, $offset, $sor ting, $e);
		}
	}

	public function countClients(){
		try{
			$this->beginTransaction();
			$result = $this->getModel('client')->countClients();
			$this->commitTransaction();
			return $result;
		}catch(\Exception $e){
			$this->rollBackTransaction();
			$this->processException($e);
			throw ClientModelException::canNotCountClients($limit, $offset, $sorting, $e);
		}
	}
	
	
/*	public function findAllowedClients($limit = null, $offset = null, $sorting = array()){
		try{
			$this->beginTransaction();
			$result = $this->getModel('client')->findClients($limit, $offset, $sorting);
			$this->allowedClientsCount = count($result);
			$this->commitTransaction();
			return $result;
		}catch(\Exception $e){
			$this->rollBackTransaction();
			$this->processException($e);
			throw ClientModelException::canNotFindAllowedClients($limit, $offset, $sorting, $e);
		}
	}
	
	public function countAllowedClients(){
		if($this->allowedClientsCount == null){
			$this->allowedClientsCount = count($this->findAllowedClients());
		}
		return $this->allowedClientsCount;
	}
*/	
	private function getAddressFromGoogleApi($seat) {

        $address = $seat['street'].$seat['city'].$seat['postalCode'];
        $url = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . rawurlencode($address);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt ($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        $json = curl_exec($curl);
        curl_close ($curl);
        $googleObject = json_decode($json);

        if($googleObject->status == "OK") {
            $address = $googleObject->results[0]->formatted_address;
            $address = explode(',', $address);
            $street = $address[0];
            $postal = explode(' ', $address[1]);
            $postal_code = '';
            foreach($postal as $index=>$part) {
                $postal_code .= $part;
            }
            foreach($postal as $index=>$part) {
                unset($postal[$index]);
            }
            $postal_town = implode(' ', $postal);
            $seat['street'] = $street;
            $seat['postalCode'] = $postal_code;
            $seat['city'] = $postal_town;
        }

        return $seat;

    }
	public function loadClientData($ico){
		set_time_limit(60);
		try{
			$this->beginTransaction();
			
			$ares = new Ares();
			$basic = $ares->getBasicInfo($ico);
			$rzp = $ares->getRZP($ico);
			
			$data = array(
				'ico' => $basic->getIco(),
				'dic' => $basic->getDic(),
				'name' => $basic->getBusinessName(),
				'type' => $basic->getType(),
				'seat' => $this->getAddressFromGoogleApi($basic->getSeat()),
				'managers' => array()
			);

			//#PeSp: fix to add the correct ID for country-code given by ARES
			$countryCodeNum = $data['seat']['country']['codeNum'];
			$countryModel = $this->getModel('country');
			$country = $countryModel->findCountryByCodeNum($countryCodeNum);
            if(!empty($country))
                $data['seat']['country']['id']=$country->getId();
			
			if($rzp){
				$data['managers'] = $rzp->getManagers();
			}
			
			foreach($data['managers'] as &$manager)
			{
				$countryCodeNum = $manager['address']['country']['codeNum'];
				$country = $countryModel->findCountryByCodeNum($countryCodeNum);
				if(!empty($country))
                    $manager['address']['country']['id'] = $country->getId();
			}
			
			if($data['type'] == 'tradesman'){
				$name = explode(' ', $data['name']);
				if(count($name) == 2){
					$data['name'] = $name[1] . ' ' . $name[0];
				}
			}
			
			$this->commitTransaction();
			return $data;
		}catch(\Exception $e){
			$this->rollBackTransaction();
			$this->processException($e);
			throw ClientModelException::canNotLoadClientDataByIco($ico);
		}
	}
	
	public function updateClientData($data) {
		set_time_limit(60);

		try{
			$this->beginTransaction();
			// find current client
			$client = $this->getModel('client')->findClient($data->id);
			if(!$client instanceof Client){
				throw ClientModelException::canNotUpdateClientBecauseClientNotFound();
			}

			$ares = new Ares();
			$basic = $ares->getBasicInfo($client->identificationNumber);
			$rzp = $ares->getRZP($client->identificationNumber);
			
			$info = array(
				'name' => $basic->getBusinessName(),
				'type' => $basic->getType(),
				'seat' => $basic->getSeat(),
				'managers' => $rzp->getManagers()
			);

			$country = $this->getModel('country')->findCountryByCodeNum($info['seat']['country']['codeNum']);
			$address = $this->getModel('address')->createAddress($country, $info['seat']['city'], $info['seat']['postalCode'], $info['seat']['street']);

			$client->clearManagers();

			$managers = array();
			foreach($info['managers'] as $m) {
				$country = $this->getModel('country')->findCountryByCodeNum($m['address']['country']['codeNum']);
				$addr = $this->getModel('address')->createAddress($country, $m['address']['city'], $m['address']['postalCode'], $m['address']['street']);
				$man = $this->getModel('contact')->createContact($addr, $m['firstName'], $m['lastName'], (array)$m);
				$managers[] = $man;
			}
			
			$client->setName($info['name']);
			$client->setType($info['type']);
			$client->setSeat($address);
			foreach($managers as $man) {
				$client->addManager($man);
			}
			$user = $this->getModel('user')->getCurrentUser();
			$this->getModel('entityHistoryItem')->createEntityHistoryItem($user, $client, false);
			$this->getModel('client')->save($client);
			$this->commitTransaction();

			return array("success");
		}catch(\Exception $e){
			$this->rollBackTransaction();
			$this->processException($e);
			throw ClientModelException::canNotUpdateClient($e);
		}
	}
	
	public function updateClient($data){
		try{
			$this->beginTransaction();
			// find current client
			$client = $this->getModel('client')->findClient($data->id);
			if(!$client instanceof Client){
				throw ClientModelException::canNotUpdateClientBecauseClientNotFound();
			}

            /*
             * Update ico (which is identificationNumber)
             */
            if(!isset($data->restore))
                $data->restore = false;
            if(!isset($data->identificationNumber)) $data->identificationNumber = $data->ico;
				
			$seatObj = (object)$data->seat;
			$country = (object)$seatObj->country;


            if(isset($country->codeNum))
			{
				$countryNum = $country->codeNum;
				$country = $this->getModel('country')->findCountryByCodeNum($countryNum);
			}else{
				$country = $this->getModel('country')->findCountryById($country->id);
            }

			$managers = array();
			if(isset($data->managers) && !empty($data->managers))
			{
				foreach($data->managers as $manager)
				{
					$obj = (object)$manager;
					$id = intval($obj->id);
					$m = $this->getModel('contact')->findContact($id);
					if(!$m instanceof Contact)
					{
						$country = $this->getModel('country')->findCountryByCodeNum($manager->address->country->codeNum);
						$address = $this->getModel('address')->createAddress($country, $manager->address->city, $manager->address->postalCode, $manager->address->street);
						$m = $this->getModel('contact')->createContact($address, $manager->firstName, $manager->lastName, (array)$manager);
					}
					$managers[] = $m;
				}
				//unset($data->managers);
			}


            $contacts = array();
			if(isset($data->contacts) && !empty($data->contacts))
			{
				foreach($data->contacts as $contact)
				{
					$obj = (object)$contact;
					$id = intval($obj->id);
					$c = $this->getModel('contact')->findContact($id);
					if(!$c instanceof Contact)
					{
						$country = $this->getModel('country')->findCountryByCodeNum($contact->address->country->codeNum);
						$address = $this->getModel('address')->createAddress($country, $contact->address->city, $contact->address->postalCode, $contact->address->street);
						$c = $this->getModel('contact')->createContact($address, $contact->firstName, $contact->lastName, (array)$contact);
					}
					$contacts[] = $c;
				}
				//unset($data->contacts);
			}
			
			
			$maintainers = array();
			if(isset($data->maintainers) && !empty($data->maintainers))
			{
				foreach($data->maintainers as $maintainer)
				{
					$obj = (object)$maintainer;
					$id = intval($obj->id);
					$m = $this->getModel('employee')->findEmployee($id);
					if(!$m instanceof Employee)
					{
						throw ClientModelException::canNotCreateClientBecauseMaintainerNotFound();
					}
					$maintainers[] = $m;
				}
				//unset($data->maintainers);
			}
			
			
			$mainAccountant = null;
			if(isset($data->mainAccountant) && !empty($data->mainAccountant))
			{
				$obj = (object)$data->mainAccountant;
				
				$m = $this->getModel('employee')->findEmployee(intval($obj->id));
				bf($m);
				if(!$m instanceof Employee)
				{
					throw ClientModelException::canNotCreateClientBecauseMainAccountantNotFound();
				}
				$mainAccountant = $m;
			}
			
			$financialAccountant = null;
			if(isset($data->financialAccountant) && !empty($data->financialAccountant))
			{
				$obj = (object)$data->financialAccountant;
                if($obj->id == 0) {
                    $financialAccountant = null;
                } else {
                    $m = $this->getModel('employee')->findEmployee($obj->id);
                    if(!$m instanceof Employee)
                    {
                        throw ClientModelException::canNotCreateClientBecauseFinancialAccountantNotFound();
                    }
                    $financialAccountant = $m;
                }
			}
						
			$payrollAccountant = null;
			if(isset($data->payrollAccountant) && !empty($data->payrollAccountant))
			{
				$obj = (object)$data->payrollAccountant;
                if($obj->id == 0) {
                    $payrollAccountant = null;
                } else {
                    $m = $this->getModel('employee')->findEmployee($obj->id);
                    if(!$m instanceof Employee)
                    {
                        throw ClientModelException::canNotCreateClientBecausePayrollAccountantNotFound();
                    }
                    $payrollAccountant = $m;
                }

			}
			
			$organization = null;
			if(isset($data->organization) && !empty($data->organization))
			{
				$obj = (object)$data->organization;
				$c = $this->getModel('contact')->findContact($obj->id);
				if(!$c instanceof Contact)
				{
					$country = $this->getModel('country')->findCountryByCodeNum($obj->address->country->codeNum);
					$address = $this->getModel('address')->createAddress($country, $obj->address->city, $data->organization->address->postalCode, $data->organization->address->street);
					$c = $this->getModel('contact')->createContact($address, $obj->firstName, $obj->lastName, (array)$obj);
				}
				$organization = $c;
			}
			
			//#PeSp: Ticket #94 entity history
			$user = $this->getModel('user')->getCurrentUser();
			$this->getModel('entityHistoryItem')->createEntityHistoryItem($user, $client, false);


            $seat = null;
            if(!isset($seatObj->id) || empty($seatObj->id) || $seatObj->id <0)
            {
                // seems like a new address

                // 2) create Seat = Address
                // a) find country

                if(!$country instanceof Country){
                    throw ClientModelException::canNotCreateClientBecauseSeatCountryWasNotFound($country->id);
                }
                // b) create seat
                $seat = $this->getModel('address')->createAddress($country, $seatObj->city, $seatObj->postalCode, $seatObj->street);
                if(!$seat instanceof Address){
                    throw ClientModelException::canNotCreateClientBecauseSeatIsNotValidAddress();
                }

            }else{
                // update existing seat

                //#PeSp: Ticket #94 entity history
                $user = $this->getModel('user')->getCurrentUser();
                $seat = $this->getModel('address')->findAddress($seatObj->id);
                $this->getModel('entityHistoryItem')->createEntityHistoryItem($user, $seat, false);
                $seat = $this->getModel('address')->updateAddress($seatObj, $country, $seat, $data->restore);
                //unset($data->seat);
            }
            unset($data->restore);
			$client = $this->getModel('client')->updateClient($client, (object) $data, $organization, $seat, $managers, $contacts, $maintainers, $mainAccountant, $financialAccountant, $payrollAccountant);

			$this->commitTransaction();
			return $client;
		}catch(\Exception $e){
			$this->rollBackTransaction();
			$this->processException($e);
			throw ClientModelException::canNotUpdateClient($e);
		}
	}
	
	public function deleteClient($id){
		try{
			$this->beginTransaction();
			$client = $this->getModel('client')->findClient($id);
			
			//#PeSp: Ticket #94 entity history
			$user = $this->getModel('user')->getCurrentUser();
			$this->getModel('entityHistoryItem')->createEntityHistoryItem($user, $client, true);
			
			
			$c = $this->getModel('client')->deleteClient($client);
			$this->commitTransaction();
			return $c;
	
		}catch(\Exception $e){
			$this->rollBackTransaction();
			$this->processException($e);
			throw ClientModelException::canNotDeleteClient($id, $e);
		}
	}
	
	public function createClient($clientData, $seatData, $managersData, $contactsData, $maintainersData){
		try{
			$this->beginTransaction();
			$ico = $clientData['ico'];

            //if not a PERSON without RC then check duplicity of ICO/RC
            if(!(($clientData['type'] == Client::PERSON) && empty($ico)))
            {
                // 1) check if this client does not exists in system.
                $client = $this->getModel('client')->findClientByIco($ico);
                if($client instanceof Client){
                    throw ClientModelException::canNotCreateClieantBecauseClientAlredyExists($ico);
                }
            }
			// 2) create Seat = Address
			// a) find country
			$seatCountry = $this->getModel('country')->findCountryById($seatData['country']->id);
			if(!$seatCountry instanceof Country){
				throw ClientModelException::canNotCreateClientBecauseSeatCountryWasNotFound($seatData['country']->id);
			}
			// b) create seat
			$seat = $this->getModel('address')->createAddress($seatCountry, $seatData['city'], $seatData['postalCode'], $seatData['street']);
			if(!$seat instanceof Address){
				throw ClientModelException::canNotCreateClientBecauseSeatIsNotValidAddress();
			}
			// 3) createManagers form managersData
			$managers = array();
			foreach($managersData as $data){
				try{
					$data = (array) $data;
					$data['address'] = (array) $data['address'];
					$data['address']['country'] = (array) $data['address']['country'];
					// Manager is contact. Contact has address. Address has Country
					// 1) find Country
					$countryCode = $data['address']['country']['codeNum'];
					if(empty($countryCode)){
						throw ClientModelException::canNotCreateClientBecauseCanNotCreateManager();
					}
					$country = $this->getModel('country')->findCountryByCodeNum($countryCode);
					if(!$country instanceof Country){
						throw ClientModelException::canNotCreateClientBecauseCanNotCreateManager();
					}
					$address = $this->getModel('address')->createAddress($country, $data['address']['city'], $data['address']['postalCode'], $data['address']['street']);
					if(!$address instanceof Address){
						throw ClientModelException::canNotCreateClientBecauseCanNotCreateManager();
					}
					$data['type'] = 'company-manager';
					$contact = $this->getModel('contact')->createContact($address, $data['firstName'], $data['lastName'], $data);
					if(!$contact instanceof Contact){
						throw ClientModelException::canNotCreateClientBecauseCanNotCreateManager();
					}
					$managers[] = $contact;
				}catch(\Exception $e){
					continue;
				}
			}

			// 4) create contacts form contactsData
			$contacts = array();
			foreach($contactsData as $data){
				throw ClientModelException::notImplementedYet();
			}
			if(empty($contacts) && $clientData['type'] != Client::COMPANY){
				$contacts = $managers;
				foreach($contacts as $contact){
					$contact->setType('tradesman');
				}
			}
			
			// 5) maintainers 
			$maintainers = array();
			foreach($maintainersData as $id){
				$maintainer = $this->getModel('employee')->findEmployee($id);
				if(!$maintainer instanceof Employee){
					throw ClientModelException::canNotCreateClientBecauseMaintainerNotFound($id);
				}
				$maintainers[] = $maintainer;
			}
			
			$data = (object)$clientData;
			
			$mainAccountant = null;
			if(isset($data->mainAccountant) && !empty($data->mainAccountant))
			{
			    $obj = (object)$data->mainAccountant;
			
			    $m = $this->getModel('employee')->findEmployee(intval($obj->id));
			    bf($m);
			    if(!$m instanceof Employee)
			    {
			        throw ClientModelException::canNotCreateClientBecauseMainAccountantNotFound();
			    }
			    $mainAccountant = $m;
			}
			$clientData['mainAccountant'] = $mainAccountant;
				
			$financialAccountant = null;
			if(isset($data->financialAccountant) && !empty($data->financialAccountant))
			{
			    $obj = (object)$data->financialAccountant;
			    $m = $this->getModel('employee')->findEmployee($obj->id);
			    if(!$m instanceof Employee)
			    {
			        throw ClientModelException::canNotCreateClientBecauseFinancialAccountantNotFound();
			    }
			    $financialAccountant = $m;
			}
			$clientData['financialAccountant'] = $financialAccountant;
			
			$payrollAccountant = null;
			if(isset($data->payrollAccountant) && !empty($data->payrollAccountant))
			{
			    $obj = (object)$data->payrollAccountant;
			    $m = $this->getModel('employee')->findEmployee($obj->id);
			    if(!$m instanceof Employee)
			    {
			        throw ClientModelException::canNotCreateClientBecausePayrollAccountantNotFound();
			    }
			    $payrollAccountant = $m;
			}
			$clientData['payrollAccountant'] = $payrollAccountant;
				
			$organization = null;
			if(isset($data->organization) && !empty($data->organization))
			{
			    $obj = (object)$data->organization;
			    $c = $this->getModel('contact')->findContact($obj->id);
			    if(!$c instanceof Contact)
			    {
			        $country = $this->getModel('country')->findCountryByCodeNum($obj->address->country->codeNum);
			        $address = $this->getModel('address')->createAddress($country, $obj->address->city, $data->organization->address->postalCode, $data->organization->address->street);
			        $c = $this->getModel('contact')->createContact($address, $obj->firstName, $obj->lastName, (array)$obj);
			    }
			    $organization = $c;
			}
			$clientData['organization'] = $organization;
			
			
			
			bf('creating client');
			bf($clientData);
			$client = $this->getModel('client')->createClient($clientData['type'], $clientData['ico'], $clientData['name'], $seat, $clientData, $managers, $contacts, $maintainers);
			$this->commitTransaction();
			return $client;
		}catch(\Exception $e){
			$this->rollBackTransaction();
			$this->processException($e);
			throw ClientModelException::canNotCreateClient($clientData['ico'],$clientData['name']);
		}
	}

	
}

