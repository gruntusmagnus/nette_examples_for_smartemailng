<?php

namespace App\ClientModule\Model;

use App\TimeRecordModule\Model\FindClientsByIcoQuery;
use Nette\DateTime;
use Orakulum\Model\AbstractModel;

use App\CommonModule\Model\Address;

/**
 * Description of UserModel
 *
 * @author JG
 */
class ClientModel extends AbstractModel{

	protected $entityName = 'App\ClientModule\Model\Client';
	
	public $onClientCreated = array();

    public function getListQuery() {
        return new FindClientsQuery();
    }
	
	public function findAllClients(){
		try{
			return $this->getRepository()->findAll();
		}catch(\Exception $e){
			$this->processException($e);
			throw ClientModelException::canNotFindAllClients();
		}
	}

	public function findClient($id){
		try{
			return $this->getRepository()->find($id);
			/*
			$filters = array();
			$filters[] = (object)array(
					"property"=>"id",
					"value"=>$id,
					"comparison"=>"=",
			);
			$res = $this->getRepository()->fetch(new FindClientsQuery(), 0, 100, array(), $filters);
			return $res[0];
			*/
		}catch(\Exception $e){
			$this->processException($e);
			throw ClientModelException::canNotFindClient($id, $e);
		}
	}
	
	public function findClients($limit = null, $offset = null, array $sorting = array(), array $filtering = array(), $ids = array()){
		try{
            //#PeSp: Ticket #131
            $nFiltering = $this->setupShowDeletedFilter($filtering);
            return $this->getRepository()->fetch(new FindClientsQuery($ids), $offset, $limit, $sorting, $nFiltering);
        }catch(\Exception $e){
			$this->processException($e);
			throw ClientModelException::canNotFindClients($offset, $limit, $e);
		}
	}

	public function findClientsByIco($ico)
	{
		try {
			//#PeSp: Ticket #131
			$nFiltering = $this->setupShowDeletedFilter(array());
			return $this->getRepository()->fetch(new FindClientsByIcoQuery($ico), null, null, array(), $nFiltering);
		} catch (\Exception $e) {
			$this->processException($e);
			throw ClientModelException::canNotFindClients(null, null, $e);
		}
	}

	public function countClients(){
		try{
			return $this->getRepository()->count(new FindClientsQuery());
		}catch(\Exception $e){
			$this->processException($e);
			throw ClientModelException::canNotCountClients();
		}
	}
	
	public function findClientByIco($ico){
		try{
			return $this->getRepository()->findOneByIdentificationNumber($ico);
		}catch(\Exception $e){
			$this->processException($e);
			throw ClientModelException::canNotFindClientByIco($ico);
		}
	}
	
	public function deleteClient(Client $c, $isTrueDelete = false){
		try{
            //#PeSp: Ticket #131 we don't delete, we just set isDeleted = true
            if(is_bool($isTrueDelete) && $isTrueDelete)
                return $this->getRepository()->delete($c);

            $c->setIsDeleted(true);
            $this->save($c);
            return $c;
		}catch(\Exception $e){
			$this->processException($e);
			throw ClientModelException::canNotDeleteClient();
		}
	}
	
	public function createClient($type, $ico, $name, Address $seat, array $data = array(), array $managers = array(), array $contacts = array(), array $maintainers = array(), $dontSave = false){
		try{

            //if not a PERSON without RC then check duplicity of ICO/RC
            if(!$dontSave && !(($type == Client::PERSON) && empty($ico)))
            {
                $client = $this->findClientByIco($ico);
                if($client instanceof Client){
                    throw ClientModelException::canNotCreateClieantBecauseClientAlredyExists($ico);
                }
            }
			
			// datetime workaround
            $dateTimeFields = array(
                'taxesDeclarationDeliveryDate',
                'taxesDocumentsDeliveryDate',
                'roadTaxDeclarationDeliveryDate',
				'cooperationInitiated',
				'cooperationTerminated',
                'servicesProvidingUntil',
                'servicesProvidingSince',
            );

            foreach($dateTimeFields as $fieldName)
            {
                if(isset($data[$fieldName]) && is_numeric($data[$fieldName]) && intval($data[$fieldName]) != 0)
                {
                    $dtInt = intval($data[$fieldName]);

                    $offset = timezone_offset_get(new \DateTimeZone(date_default_timezone_get()),new \DateTime('@'.$dtInt));
                    $data[$fieldName] = new \DateTime('@'.($dtInt+$offset));
                }else{
                    $data[$fieldName] = null;
                }
            }

            /*
             * Have to transform all the boolean fields to correct values
             */
            $data['salaryServices'] = $this->fixBoolean($data['salaryServices']);
            $data['strictlyQuarterlyBilling'] = $this->fixBoolean($data['strictlyQuarterlyBilling']);
            if ($data['strictlyQuarterlyBilling'] === null)
                $data['strictlyQuarterlyBilling'] = false;
            $data['taxDeferral'] = $this->fixBoolean($data['taxDeferral']);
            $data['activeClient'] = $this->fixBoolean($data['activeClient']);
            $data['roadTaxPayment'] = $this->fixBoolean($data['roadTaxPayment']);

			$client = $this->getRepository()->createNew($type, $ico, $name, $seat, $data, $managers, $contacts, $maintainers);
			if($dontSave) return $client;
			$this->getRepository()->save($client);
			
			$this->onClientCreated($client);
			
			return $client;
			
		}catch(\Exception $e){
			$this->processException($e);
			throw ClientModelException::canNotCreateClient($ico, $name);
		}
	}
	
	public function updateClient(Client $client, $data, $organization = null, Address $seat, array $managers = array(), array $contacts = array(), array $maintainers = array(), $mainAccountant = null, $financialAccountant = null, $payrollAccountant = null)
	{
		try{
			if(!$client instanceof Client){
				throw ClientModelException::canNotCreateClieantBecauseClientNotFound();
			}
			
			if($data->version != $client->getVersion()){
				throw ClientModelException::canNotUpdateClientBecauseSomeoneUpdateThisRecord();
			}
				
			/* typ klienta */ 
			/* string */ 
			if($data->type != $client->getType()) {
				$client->setType($data->type);
			}
			
			/* název kontaktu */ 
			/* string */ 
			if($data->name != $client->getName()) {
				$client->setName($data->name);
			}

			/* IČO (nebo rodné číslo) */ 
			/* string */
            if(empty($data->identificationNumber) && isset($data->ico))
                $data->identificationNumber = $data->ico;

			if($data->identificationNumber != $client->getIdentificationNumber()) {
				$client->setIdentificationNumber($data->identificationNumber);
			}

            /* DIČ (daňové identifiační číslo) */
            /* string */
            if($data->dic != $client->getDic()) {
                $client->setDic($data->dic);
            }
			
			/* perioda plátby DPH */ 
			/* integer */ 
			if($data->dphPeriodicity != $client->getDphPeriodicity()) {
				$client->setDphPeriodicity($data->dphPeriodicity);
			}
			
			/* periodicita fakturace */ 
			/* integer */ 
			if($data->frequencyOfBilling != $client->getFrequencyOfBilling()) {
				$client->setFrequencyOfBilling($data->frequencyOfBilling);
			}
			
			/* způsob fakturace */ 
			if($data->typeOfBilling != $client->getTypeOfBilling()) {
				$client->setTypeOfBilling($data->typeOfBilling);
			}
			
			/* adresa sídla */ 
			if($seat != $client->getSeat()) {
				$client->setSeat($seat);
			}
			
			
			/* kontakty na manažery klienta */
			//$client->clearManagers();
			$coll = $client->getManagers();
			//remove old
			foreach($coll as $oldManager)
			{
				if(!in_array($oldManager, $managers))
					$client->removeManager($oldManager);
			}
			//add new
			foreach($managers as $manager) {
				if(!$coll->contains($manager))
					$client->addManager($manager);
			}
			
			/* kontakty na kontaktni osoby */ 
			//$client->clearContacts();
			$coll = $client->getContacts();
			//remove old
			foreach($coll as $oldContact)
			{
				if(!in_array($oldContact, $contacts))
					$client->removeContact($oldContact);
			}
			//add new
			foreach($contacts as $contact) {
				if(!$coll->contains($contact))
					$client->addContact($contact);
			}
			
			/* zaměstnanci zodpovědní za daného klienta */ 
			//$client->clearMaintainers();
			$coll = $client->getMaintainers();
			//remove old
			foreach($coll as $key => $oldMaintainer)
			{
				if(!in_array($oldMaintainer, $maintainers))
					$client->removeMaintainer($oldMaintainer);
			}
			//add new
			foreach($maintainers as $maintainer) {
				if(!$coll->contains($maintainer))
					$client->addMaintainer($maintainer);
			}
			
			
			/* stav klienta */ 
			/* string */ 
			if($data->status != $client->getStatus()) {
				$client->setStatus($data->status);
			}
			
			/* prodloužení splatnosti daně */ 
			/* string */ 
			if($data->repairPeriod != $client->getRepairPeriod()) {
				$client->setRepairPeriod($data->repairPeriod);
			}
			
			/* kontakt na statutarni organ */ 
			if($organization != $client->getOrganization()) {
				$client->setOrganization($organization);
			}
			
			/* místo výkonu činnost (kde se zpracovává účetnictví) */ 
			/* string */ 
			if($data->workPlace != $client->getWorkPlace()) {
				$client->setWorkPlace($data->workPlace);
			}
			
			/* předávání dokladů */ 
			/* string */ 
			if($data->documentsTransferType != $client->getDocumentsTransferType()) {
				$client->setDocumentsTransferType($data->documentsTransferType);
			}
			
			/* účetní služby */ 
			/* string */ 
			if($data->accountingServices != $client->getAccountingServices()) {
				$client->setAccountingServices($data->accountingServices);
			}
			
			/* mzdové služby */ 
			/* boolean */
            $extBoolean = $this->fixBoolean($data->salaryServices);
			if($extBoolean !== $client->getSalaryServices()) {
				$client->setSalaryServices($extBoolean);
			}
			
			/* čistě kvartální fakturace */ 
			/* boolean */
            $extBoolean = $this->fixBoolean($data->strictlyQuarterlyBilling);
			if($extBoolean !== $client->getStrictlyQuarterlyBilling()) {
				$client->setStrictlyQuarterlyBilling($extBoolean);
			}
			
			/* dřívější názvy */ 
			/* string */ 
			if($data->formerNames != $client->getFormerNames()) {
				$client->setFormerNames($data->formerNames);
			}

			/* Hospodářský rok */
			/* integer */
			if($data->marketYear != $client->getMarketYear() && is_numeric($data->marketYear)) {
				$client->setMarketYear($data->marketYear);
			}

			/* aktivní klient */ 
			/* boolean */
            $extBoolean = $this->fixBoolean($data->activeClient);
			if($extBoolean !== $client->getActiveClient()) {
				$client->setActiveClient($extBoolean);
			}
			
			/* skupina firem */ 
			/* text */ 
			if($data->companiesGroup != $client->getCompaniesGroup()) {
				$client->setCompaniesGroup($data->companiesGroup);
			}

			/* zahajeni spoluprace */
			/* datetime */

			if(isset($data->cooperationInitiated) && is_numeric($data->cooperationInitiated)) {

				$time = intval($data->cooperationInitiated);
				$offset = timezone_offset_get(new \DateTimeZone(date_default_timezone_get()),new \DateTime('@'.$time));
				$dt = new \DateTime('@'.($time+$offset));

			}
			else {
				$dt = null;
			}
			if($dt != $client->getCooperationInitiated()) {
				$client->setCooperationInitiated($dt);
			}


			/* ukonceni spoluprace */
			/* datetime */

			if(isset($data->cooperationTerminated) && is_numeric($data->cooperationTerminated)) {

				$time = intval($data->cooperationTerminated);
				$offset = timezone_offset_get(new \DateTimeZone(date_default_timezone_get()),new \DateTime('@'.$time));
				$dt = new \DateTime('@'.($time+$offset));

			}
			else {
				$dt = null;
			}
			if($dt != $client->getCooperationTerminated()) {
				$client->setCooperationTerminated($dt);
			}
			
			
			/* poskytování služeb od */ 
			/* datetime */ 
			
			if(isset($data->servicesProvidingSince) && is_numeric($data->servicesProvidingSince))
			{
				$since = intval($data->servicesProvidingSince);
			
				$offset = timezone_offset_get(new \DateTimeZone(date_default_timezone_get()),new \DateTime('@'.$since));
				$dt = new \DateTime('@'.($since+$offset));
			}else{
				$dt = null;
			}
				
			if($dt != $client->getServicesProvidingSince()) {
				$client->setServicesProvidingSince($dt);
			}
			
			
			/* poskytování služeb do */ 
			/* datetime */
			
			if(isset($data->servicesProvidingUntil) && is_numeric($data->servicesProvidingUntil))
			{
				$until = intval($data->servicesProvidingUntil);
					
				$offset = timezone_offset_get(new \DateTimeZone(date_default_timezone_get()),new \DateTime('@'.$until));
				$dt = new \DateTime('@'.($until+$offset));
			}else{
				$dt = null;
			}
			
			if($dt != $client->getServicesProvidingUntil()) {
				$client->setServicesProvidingUntil($dt);
			}
			
			/* hlavní účetní */ 
			if($mainAccountant != $client->getMainAccountant()) {
				$client->setMainAccountant($mainAccountant);
			}
			
			/* finanční účetní */ 
			if($financialAccountant != $client->getFinancialAccountant()) {
				$client->setFinancialAccountant($financialAccountant);
			}
			
			/* mzdová účetní */ 
			if($payrollAccountant != $client->getPayrollAccountant()) {
				$client->setPayrollAccountant($payrollAccountant);
			}
			
			/* účetní software */ 
			/* string */ 
			if($data->accountingSoftware != $client->getAccountingSoftware()) {
				$client->setAccountingSoftware($data->accountingSoftware);
			}
			
			/* Typ zpracová */ 
			/* string */ 
			if($data->processingType != $client->getProcessingType()) {
				$client->setProcessingType($data->processingType);
			}
			
			/* plátcovství silniční daně */ 
			/* boolean */
            $extBoolean = $this->fixBoolean($data->roadTaxPayment);
			if($extBoolean !== $client->getRoadTaxPayment()) {
				$client->setRoadTaxPayment($extBoolean);
			}
			
			/* výplatní termín mezd */ 
			/* integer */ 
			if($data->salaryPayOffDay != $client->getSalaryPayOffDay()) {
				$client->setSalaryPayOffDay($data->salaryPayOffDay);
			}
			
			/* zálohy na daň z příjmu */ 
			/* string */ 
			if($data->vatDepositPeriod != $client->getVatDepositPeriod()) {
				$client->setVatDepositPeriod($data->vatDepositPeriod);
			}
			
			/* fakturace od subjektu */ 
			/* string */ 
			if($data->billingBy != $client->getBillingBy()) {
				$client->setBillingBy($data->billingBy);
			}

            /* smlouva */
            /* string */
            if($data->contract != $client->getContract()) {
                $client->setContract($data->contract);
            }

            /* poznámka */
            /* string */
            if($data->note != $client->getNote()) {
                $client->setNote($data->note);
            }

            /* datum předání SD klientovi */
            /* datetime */
            if(isset($data->roadTaxDeclarationDeliveryDate) && is_numeric($data->roadTaxDeclarationDeliveryDate))
            {
                $tempDT = intval($data->roadTaxDeclarationDeliveryDate);

                $offset = timezone_offset_get(new \DateTimeZone(date_default_timezone_get()),new \DateTime('@'.$tempDT));
                $dt = new \DateTime('@'.($tempDT+$offset));
            }else{
                $dt = null;
            }

            if($dt != $client->getRoadTaxDeclarationDeliveryDate()) {
                $client->setRoadTaxDeclarationDeliveryDate($dt);
            }

            /* počet aut v SD */
            /* int */
            if($data->roadTaxNumberOfCars != $client->getRoadTaxNumberOfCars()) {
                $client->setRoadTaxNumberOfCars($data->roadTaxNumberOfCars);
            }

            /* datum předání DP */
            /* datetime */
            if(isset($data->taxesDeclarationDeliveryDate) && is_numeric($data->taxesDeclarationDeliveryDate))
            {
                $tempDT = intval($data->taxesDeclarationDeliveryDate);

                $offset = timezone_offset_get(new \DateTimeZone(date_default_timezone_get()),new \DateTime('@'.$tempDT));
                $dt = new \DateTime('@'.($tempDT+$offset));
            }else{
                $dt = null;
            }

            if($dt != $client->getTaxesDeclarationDeliveryDate()) {
                $client->setTaxesDeclarationDeliveryDate($dt);
            }

            /* datum předání podkladů pro DP */
            /* datetime */
            if(isset($data->taxesDocumentsDeliveryDate) && is_numeric($data->taxesDocumentsDeliveryDate))
            {
                $tempDT = intval($data->taxesDocumentsDeliveryDate);

                $offset = timezone_offset_get(new \DateTimeZone(date_default_timezone_get()),new \DateTime('@'.$tempDT));
                $dt = new \DateTime('@'.($tempDT+$offset));
            }else{
                $dt = null;
            }

            if($dt != $client->getTaxesDocumentsDeliveryDate()) {
                $client->setTaxesDocumentsDeliveryDate($dt);
            }

            /* odklad DP */
            /* boolean */
            $extBoolean = $this->fixBoolean($data->taxDeferral);
            if($extBoolean !== $client->getTaxDeferral()) {
                $client->setTaxDeferral($extBoolean);
            }

            /* external disk path */
            /* string */
            if($data->externalDiskPath != $client->getExternalDiskPath()) {
                $client->setExternalDiskPath($data->externalDiskPath);
            }

            /* Data access fee */
            if($data->dataAccessFee != $client->getDataAccessFee()) {
                $client->setDataAccessFee($data->dataAccessFee);
            }

            $this->save($client);

			$this->onClientCreated($client);
				
			return $client;
				
		}catch(\Exception $e){
			$this->processException($e);
			throw ClientModelException::canNotUpdateClient($e);
		}
	}

    /*
     * manages the 3state boolean, i.e. true,false and null
     */
    private function fixBoolean($boolean)
    {
        $extBoolean = $boolean;
        if(trim($extBoolean) == "true" || (is_bool($extBoolean) && $extBoolean === true) || (is_numeric($extBoolean) && intval($extBoolean) == 1 ) ) $extBoolean = true;
        elseif(trim($extBoolean) == "false" || (is_bool($extBoolean) && $extBoolean === false) || (is_numeric($extBoolean) && intval($extBoolean) == 0 )) $extBoolean = false;
        else $extBoolean = null;
        return $extBoolean;
    }

    /*
     * History -> Frontend mapping
     */
    protected function getHistoryFrontendMapping() {
        return array(
            'identificationNumber' => 'ico'
        );
    }


    /**
     * Set data access fee as paid.
     * @param Client $client
     * @return Client
     * @throws
     */
    public function setDataAccessFeePaid(Client $client) {
        try {
            $client->setDataAccessFeePaid(true);
            $this->save($client);
            return $client;

        } catch(\Exception $e) {
            $this->processException($e);
            throw ClientModelException::canNotUpdateClient($e);
        }
    }
}

