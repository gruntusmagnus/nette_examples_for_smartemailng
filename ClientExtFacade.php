<?php

namespace App\ClientModule\Model;

use Nette\Diagnostics\FireLogger;
use Nette\Environment;
use Orakulum\ExtDirect\Response;

use Orakulum\Export\Excel;

use Nette\Utils\Strings,Nette\Diagnostics\Debugger;

use App\CommonModule\Model\AddressExtFacade,
	App\CommonModule\Model\ContactExtFacade,
	App\EmployeeModule\Model\EmployeeExtFacade;

use
App\CommonModule\Model\Address,
App\EmployeeModule\Model\Employee,
App\CommonModule\Model\Contact;

// Invoicer
use Orakulum\Components\Pohoda\Invoicer,
     Orakulum\Components\Pohoda\XmlGenerator;


/**
 * Description of UserFacade
 *
 * @author JG
 */
class ClientExtFacade extends ClientFacade{

	private $exportDir;
    private $context;

	public function __construct($exportDir, \Nette\Di\Container $context){
        $this->context = $context;
		$this->setExportDir($exportDir);
	}
	
	
	public function setExportDir($exportDir){
		$d = realpath($exportDir);
		if(!$d){
			throw ClientModelException::directoryNotFound($exportDir);
		}
		$this->exportDir = $d;
		return $this;
	}
	
	public function create(){
		bf(func_get_args());
		return new Response(true, 'Successfully created.');
	}
	
	public function verifyIco($args)
	{

        $collection = $this->getModel('client')->findClientsByIco($args->ico);

        $array = array();

        foreach ($collection as $client) {
            $array[] = static::clientToArray($client);
        }
        if (!empty($array))
            return new Response(((object)array('icoExists' => true, 'clientId' => $array[0]['id'])), 'The ICO already exists in the system');
        else
            return new Response(((object)array('icoExists' => false)), 'This ICO has not been used yet');
    }

    public function verifyRc($args)
    {

        $normalizedIcoPartOne = substr($args->ico,0,6);
        $normalizedIcoPartTwo = substr($args->ico, 6);
        if(!is_numeric($normalizedIcoPartTwo))
            $normalizedIcoPartTwo = substr($normalizedIcoPartTwo,1);


        $normalizedIco = $normalizedIcoPartOne.'/'.$normalizedIcoPartTwo;

        $collection = $this->getModel('client')->findClientsByIco($normalizedIco);

        $array = array();

        foreach ($collection as $client) {
            $array[] = static::clientToArray($client);
        }
        if (!empty($array))
            return new Response(((object)array('rcExists' => true, 'clientId' => $array[0]['id'])), 'The RC already exists in the system');
        else
            return new Response(((object)array('rcExists'=>false)), 'This RC has not been used yet');
    }
	
	public function readOne($args)
	{
		$filters = array();
		$filters[] = (object)array(
				"property"=>"id",
				"value"=>$args->id,
				"comparison"=>"=",
		);
	
		$args->filter = $filters;
	
		//$res = $this->read((object)array_merge((array)$args,array('filter'=>$filters)))->getResult();
		$res = $this->read($args)->getResult();
		if(!empty($res['items']))
			return new Response($res['items'], 'Successfully read');
		else
			return new Response(false, 'Could not find such ID','error');
	}
	
	public function read($args){
		if(isset($args->sort)){
			$sort = $args->sort;
		}else{
            $s = array('property'=>'name', 'direction'=> 'ASC');
			$sort = array((object) $s);
		}
		
		if(isset($args->filter)){
			$filters = $args->filter;
		}else{
			$filters = array();
		}

		$collection = $this->findClients($args->limit, $args->start, $sort, $filters);


        $array = array();

        foreach($collection as $client){
            $clientArray = static::clientToArray($client);
            if(isset($args->withTaxDates)) {
                /** @var $taxDatesModel TaxDateModel **/
                /** @var $taxDate TaxDate **/
                $taxDatesModel = $this->getModel('taxdate');
                $taxDates = $taxDatesModel->findTaxDates($client);
                foreach($taxDates as $taxDate) {
                    $clientArray[$this->dashesToCamelCase($taxDate->getType())] = $taxDate->getSubmissionDate()->getTimestamp();
                }
            }
			$array[] = $clientArray;
        }

        return new Response(array('items' => $array, 'total' => $this->countClients()), 'Successfully read');
    }

    /**
     * TODO: Move (create) helper class
     * @param $string
     * @param bool $capitalizeFirstCharacter
     * @return mixed
     */
    function dashesToCamelCase($string, $capitalizeFirstCharacter = false)
    {

        $str = str_replace(' ', '', ucwords(str_replace('-', ' ', $string)));

        if (!$capitalizeFirstCharacter) {
            $str[0] = strtolower($str[0]);
        }

        return $str;
    }

    /**
     * Get prices for client
     *
     * @param $args
     */
    public function prices($args) {
        bf(func_get_args());
        if (isset($args->id))
            $id = $args->id;
        else
            $id = 0;

        /*
         * First check if the client really exists
         */
        $client = $this->getModel('client')->findClient($id);
        if(!$client instanceof Client){
            throw ClientModelException::canNotUpdateClientBecauseClientNotFound();
        }

        $prices = $this->getModel('price')->findPricesByClient($client->getId());

        $ret = array();
        foreach($prices as $pr) {
            $ret[] = $this->getModel('price')->itemToArray($pr);
        }

        return new Response(array('items' => $ret), 'Successfully read');
    }

    /**
     * Submits Tax Date for client
     * @param $args
     * @throws
     * @return \Orakulum\ExtDirect\Response
     */
    public function submitTaxDate($args) {
        if (isset($args->id))
            $id = $args->id;
        else
            $id = 0;

        $type = (isset($args->type))?$args->type:"";
        $count = (isset($args->count))?$args->count:1;
        $date = (isset($args->date))?$args->date:date('Y-m-d');

        /*
         * First check if the client really exists
         */
        $client = $this->getModel('client')->findClient($id);
        if(!$client instanceof Client){
            throw ClientModelException::canNotFindClients();
        }

        $taxDateData = array(
            'client' => $client,
            'type' => $type,
            'count' => $count,
            'submissionDate' => new \DateTime($date)
        );

        $taxDate = $this->getModel('taxdate')->createTaxDate($taxDateData);
        return new Response(array('data'=>$taxDateData), 'Successfuly added');
    }


    /**
     * Handling pohoda files REST API
     *
     * @param $args
     * @return Response
     * @throws
     */
    public function pohodaFiles($args) {
        $clientId = $args->clientId;
        $method = $args->method;

        $pohodaFileId = null;
        if (isset($args->pohodaFileId))
            $pohodaFileId = $args->pohodaFileId;

        if($pohodaFileId) {
            switch($method)  {
                case 'DELETE':
                    return $this->pohodaFileDeleteHandle($pohodaFileId);
                    break;
                case 'POST':
                    return $this->pohodaFilePostHandle($clientId, $args->pohodaFile, $pohodaFileId);
                    break;
                case 'GET':
                default:
                   return $this->pohodaFileGetOneHandle($pohodaFileId);
            }

            return new Response($data, 'Successfuly read');
        } else {
            return $this->pohodaFileGetHandle($clientId);
        }
    }

    /**
     * Delete pohoda file
     *
     * @param $pohodaFileId
     * @return Response
     */
    protected function pohodaFileDeleteHandle($pohodaFileId) {
        $this->getModel('pohodafile')->delete($pohodaFileId);
        return new Response(array('deleted' => true), 'Successfuly read');
    }

    /**
     * Get pohoda file
     *
     * @param $pohodaFileId
     * @return Response
     */
    protected function pohodaFileGetOneHandle($pohodaFileId) {
        $pohodaFile = $this->getModel('pohodafile')->get($pohodaFileId);
        $pohodaFileArray = $this->getModel('pohodafile')->itemToArray($pohodaFile);
        return new Response(array('item' => $pohodaFileArray), 'Successfuly read');

    }

    /**
     * Get pohoda files by client
     *
     * @param $clientId
     * @return Response
     */
    protected function pohodaFileGetHandle($clientId) {
        $pohodaFiles = $this->getModel('pohodafile')->getByClient($clientId);
        $pohodaFilesArray = $this->getModel('pohodafile')->collectionToArray($pohodaFiles);
        return new Response(array('items'=>$pohodaFilesArray), 'Successfuly read');
    }

    /**
     * Create or update pohoda file
     * TODO: Make PUT request to update
     *
     * @param $clientId
     * @param $pohodaFile
     * @param $pohodaFileId
     * @return Response
     * @throws
     */
    protected function pohodaFilePostHandle($clientId, $pohodaFile, $pohodaFileId) {
        $client = $this->getModel('client')->findClient($clientId);
        if(!$client instanceof Client){
            throw ClientModelException::canNotFindClients();
        }
        $pohodaFile->client = $client;
        if($pohodaFileId > 0) {
            $pohodaFile = $this->getModel('pohodafile')->save($pohodaFile);
        } else {
            $pohodaFile = $this->getModel('pohodafile')->createPohodaFile($pohodaFile);
        }
        $pohodaFileArray = $this->getModel('pohodafile')->itemToArray($pohodaFile);
        return new Response(array('item' => $pohodaFileArray), 'Successfuly read');
    }

    /**
     * Calculate invoice for the client
     * @param $args
     * @throws
     * @return \Orakulum\ExtDirect\Response
     */
    public function calculateInvoice($args) {
        if (isset($args->id))
            $id = $args->id;
        else
            $id = 0;
        $year = (isset($args->year))?$args->year:0;
        $month = (isset($args->month))?$args->month:0;
        $chargeExtra = (isset($args->chargeExtra))?preg_split("/,/",$args->chargeExtra):array();
        $fakeNow = (isset($args->date))?$args->date:date('Y-m-d');
        $taxableDate = (isset($args->taxableDate))?$args->taxableDate:false;
        $ignoreOtherInvoices = (isset($args->ignoreOtherInvoices))?(($args->ignoreOtherInvoices == 'true')?true:false):false;
        $dataAccessFee = (isset($args->dataAccessFee))?(($args->dataAccessFee == 'true')?true:false):false;
        /*
         * First check if the client really exists
         */
        $client = $this->getModel('client')->findClient($id);
        if(!$client instanceof Client){
            throw ClientModelException::canNotFindClients();
        }

        $invoicer = new Invoicer($client, $this->context, $ignoreOtherInvoices, $year, $dataAccessFee,$month);

        $invoice_data = $invoicer->calculateInvoice($year, $month, $fakeNow, $taxableDate, $chargeExtra);

        $invoice_data['args'] = new \stdClass();
        $invoice_data['args']->year = $year;
        $invoice_data['args']->month = $month;
        $invoice_data['args']->ignoreOtherInvoices = $ignoreOtherInvoices;

        if($this->isInvoiceWithoutError($invoice_data))
        {
            $invoice = $this->convertInvoiceDataToInvoiceModel($invoice_data, $client);
            $dataForXmlGenerator = $this->convertInvoiceModelForXmlGenerator($invoice, $invoice_data['invoiceItems']);
            $xmlGenerator = new XmlGenerator();
            $xmlGenerator->setData($dataForXmlGenerator)->setMainElement('dat:dataPack')->generateXML()->save('./../temp/exports/generatedXML.xml');
        }
        return new Response(array('data'=>$invoice_data), 'Successfuly read');
    }

    /**
     * Get invoice items
     * @param $args
     * @throws
     * @return \Orakulum\ExtDirect\Response
     */
    public function getInvoiceItems($args) {
        if (isset($args->id))
            $id = $args->id;
        else
            $id = 0;
        $year = (isset($args->year))?$args->year:0;
        $month = (isset($args->month))?$args->month:0;
        $fakeNow = (isset($args->date))?$args->date:date('Y-m-d');
        $ignoreOtherInvoices = (isset($args->ignoreOtherInvoices))?(($args->ignoreOtherInvoices == 'true')?true:false):false;


        /*
         * First check if the client really exists
         */
        $client = $this->getModel('client')->findClient($id);
        if(!$client instanceof Client){
            throw ClientModelException::canNotFindClients();
        }

        $invoicer = new Invoicer($client, $this->context, $ignoreOtherInvoices, $year, false ,$month);
        $invoice_data = $invoicer->calculateInvoice($year, $month, $fakeNow);
        if($this->isInvoiceWithoutError($invoice_data))
        {
            $items = $invoice_data['rawItems'];
        } else {
            $items = $invoice_data;
        }

        return new Response(array('data'=>$items), 'Successfuly read');
    }



    /**
     * Check error
     * @param array $invoice
     * @return bol
     */
    private function isInvoiceWithoutError($invoice) {
        return !isset($invoice['error']);
    }

    /**
     * Creating invoice model without saving to database.
     * @param array $data
     * @param Client $clientModel
     * @return Invoice
     */
    private function convertInvoiceDataToInvoiceModel($data, $clientModel) {
        bf('convertInvoiceDataToInvoiceModel');
        $note = '';
        $dates = (object) $data['additionalInfo']['invoiceDates'];
        $client = (object) $data['additionalInfo']['client'];
        $supplier = (object) $this->getModel('invoice')->suppliers[$data['additionalInfo']['supplier']['name']];
        foreach($data['invoiceItems'] as $item) {
            if($item->type != 'hours') {
                $note .= $item->name." (mnozstvi: ".$item->number_of_units.", cena celkem bez DPH: ".$item->price.")\n";
            }
        }
        if(isset($data['info']['Hodiny'])) {
            foreach($data['info']['Hodiny'] as $item) {
                $time = $this->convertHoursToHoursAndMinutes($item['doba']);
                $note .= $item['predmet'].' - '.$time."\n";
            }
        }
        $countItems = 0;
        foreach ($data['rawItems'] as $itemsBlock){
            $countItems = $countItems  + count($itemsBlock);
        }

        if (!isset($data['arrayItems']))
            $data['arrayItems'] = array();


        $invoiceData = array(
            'dueDate' => new \DateTime($dates->dueDate),
            'taxableSupplyDate' => new \DateTime($dates->taxableSupply),
            'issueDate' => new \DateTime($dates->created),
            'billingDateFrom' => new \DateTime($dates->billingDateFrom),
            'billingDateTo' => new \DateTime($dates->billingDateTo),
            'clientPohodaID' => $client->pohodaID,
            'clientName' => $client->name,
            'clientIdentificationNumber' => $client->identificationNumber,
            'clientDic' => $client->dic,
            'clientCountry' => $client->country,
            'clientCity' => $client->city,
            'clientPostalCode' => $client->postalCode,
            'clientStreet' => $client->street,
            'supplierName' => $supplier->name,
            'supplierIdentificationNumber' => $supplier->identificationNumber,
            'supplierDic' => $supplier->dic,
            'supplierCountry' => $supplier->country,
            'supplierCity' => $supplier->city,
            'supplierPostalCode' => $supplier->postalCode,
            'supplierStreet' => $supplier->street,
            'client' => $clientModel,
            'note' => $note,
            'withWages' => $data['withWages'],
            'withHours' => $data['withHours'],
            'exposureYear' => $data['args']->year,
            'exposureMonth' => $data['args']->month,
            'ignoreOtherInvoices' => $data['args']->ignoreOtherInvoices,
            'invoiceItemsCounts' => $data['arrayItems'],
            'countItems' => $countItems
        );

        $invoice = $this->getModel('invoice')->createInvoice($invoiceData);
        return $invoice;
    }

    private function convertHoursToHoursAndMinutes($hours){
        $onlyHours = explode(".",$hours);
        $minutes = 0;
        if(isset($onlyHours[1])) {
            $minutes =  ceil(($onlyHours[1]) * 60 / 1000);
        }
        $hoursWithMinutes = $this->addingZeroToNumber($onlyHours[0]).':'.$this->addingZeroToNumber($minutes);
        return $hoursWithMinutes;
    }

    private function addingZeroToNumber($number){
        if($number < 10) $number = '0'.$number;
        return $number;
    }

    public function deleteInvoices() {
        bf('deleteInvoices');
        $this->getModel('invoice')->deleteInvoices();
        return new Response(array(), 'Successfuly deleted');
    }
    /**
     * Creating asociative array for xml generator.
     * @param Invoice $invoice
     * @param array $invoiceItems
     * @return array
     */
    private function convertInvoiceModelForXmlGenerator($invoice, $invoiceItems)
    {
        $data = array('dat:dataPack' => array('dat:dataPackItem' => array('inv:invoice' => array())));
        $invoiceArray = array();
        $invoiceArray['inv:invoiceHeader'] = array(
            'inv:invoiceType' => 'issuedInvoice',
            'inv:date' => $invoice->issueDate->format('Y-m-d'),
            'inv:dateTax' => $invoice->taxableSupplyDate->format('Y-m-d'),
            'inv:dateDue' => $invoice->dueDate->format('Y-m-d'),
            'inv:classificationVAT' => array(
//                'typ:id' => '57' // ID v členění DPH (z nějakého důvodu nefunguje, chtělo by zjistit proč
                'typ:classificationVATType' => 'inland'
            ),
        );

        $pohodaID = $invoice->getClientPohodaID();
        if(is_null($pohodaID)) {
            // Without connection to "Adresář"
            $invoiceArray['inv:invoiceHeader']['inv:partnerIdentity'] = array();
            $invoiceArray['inv:invoiceHeader']['inv:partnerIdentity']['typ:address'] = array(
                'typ:company' => $invoice->clientName,
                'typ:city' => $invoice->clientCity,
                'typ:street' => $invoice->clientStreet,
                'typ:zip' => $invoice->clientPostalCode,
                'typ:ico' => $invoice->clientIdentificationNumber,
                'typ:dic' => $invoice->clientDic,
    //            'typ:country' => $invoice->clientCountry,  Country id from Pohoda
            );
        } else {
            // With connection to "Adresář"
            $invoiceArray['inv:invoiceHeader']['inv:partnerIdentity'] = array(
                'typ:id' => $invoice->getClientPohodaID()
            );
        }


        $invoiceArray['inv:invoiceHeader']['inv:myIdentity'] = array();
        $invoiceArray['inv:invoiceHeader']['inv:myIdentity']['typ:address'] = array(
            'typ:company' => $invoice->supplierName,
            'typ:city' => $invoice->supplierCity,
            'typ:street' => $invoice->supplierStreet,
            'typ:zip' => $invoice->supplierPostalCode,
            'typ:ico' => $invoice->supplierIdentificationNumber,
            'typ:dic' => $invoice->supplierDic,
//            'typ:country' => $invoice->supplierCountry, Country id from Pohoda
        );
//        $invoiceArray['inv:invoiceHeader']['inv:note'] = $invoice->note; Note removed from invoice import
        $invoiceArray['inv:invoiceDetail'] = array();
        $invoiceArray['inv:invoiceDetail']['inv:invoiceItem_MULTIARRAY'] = array();
        foreach ($invoiceItems as $invoiceItem) {
            $invoiceArray['inv:invoiceDetail']['inv:invoiceItem_MULTIARRAY'][] = array(
                'inv:text' => $invoiceItem->name,
                'inv:quantity' => $invoiceItem->number_of_units,
                'inv:payVAT' => 'false',
                'inv:rateVAT' => 'high',
                'inv:homeCurrency' => array(
                    'typ:unitPrice' => $invoiceItem->price_per_unit,
                    'typ:price' => $invoiceItem->price_per_unit * $invoiceItem->number_of_units,
                    'typ:priceVAT' => ($invoiceItem->price_per_unit_with_vat - $invoiceItem->price_per_unit) * $invoiceItem->number_of_units,
                )
            );
        }
        $data['dat:dataPack']['dat:dataPackItem']['inv:invoice'] = $invoiceArray;
        return $data;
    }


    /**
     * @param Client $client
     * @param null $maxLevel
     * @param int $level
     * @return array|int
     */
    public static function clientToArray(Client $client, $maxLevel = null, $level = 1){
        static $list = array();
        if(in_array($client, $list, true) || ($maxLevel !== null && $level > $maxLevel)){
            //recursion
            return $client->getId();
        }else{
            $list[] = $client;

            $mainAccountant = $client->getMainAccountant();
            if(!empty($mainAccountant))
                $mainAccountant = EmployeeExtFacade::employeeToArray($mainAccountant, $maxLevel, $level +1);

            $financialAccountant = $client->getFinancialAccountant();
            if(!empty($financialAccountant))
                $financialAccountant = EmployeeExtFacade::employeeToArray($financialAccountant, $maxLevel, $level +1);

            $payrollAccountant = $client->getPayrollAccountant();
            if(!empty($payrollAccountant))
                $payrollAccountant = EmployeeExtFacade::employeeToArray($payrollAccountant, $maxLevel, $level +1);


            //#PeSp: Ticket #115
            // a client needs to be checked if its is active, his contract is on
            // and he has not been modified for a year
            $needsChecking = false;
            $now = new \DateTime('now');
            $diffInterval = $now->diff($client->getModified());
            $gd = $client->getServicesProvidingUntil();

            //if($diffInterval->d >= 1 && $client->getActiveClient()
            //    && ( (empty($gd) || $now <= $gd) ))
            //if($diffInterval->y >= 1 && $client->getActiveClient()
            if($diffInterval->y >= 1 && strcmp($client->getStatus(),'active') == 0
                && ( (empty($gd) || $now <= $gd) ))
                $needsChecking = true;

            $contacts = array_map(function($contact)use($maxLevel, $level){
                return ContactExtFacade::contactToArray($contact, $maxLevel, $level +1);
            }, $client->getContacts()->filter(function($c) {
                if($c->isDeleted<1) return $c;
            } )->toArray());

            $contacts = array_values($contacts);
            //dump($contacts);
            //exit;

            //$contacts = $client->getContacts();
            $contactBusiness = array();
            $contactSocialHealthFinancial = array();
            $contactUniversal = array();
            $contactOrganization = array();

            foreach($contacts as $c) {

                switch($c['type']) {
                    case 'business-contact':
                        $contactBusiness[] = $c;
                        break;
                    case 'health-care-office':
                        $contactSocialHealthFinancial[] = $c;
                        break;
                    case 'social-care-office':
                        $contactSocialHealthFinancial[] = $c;
                        break;
                    case 'organization':
                        $contactOrganization[] = $c;
                        break;
                    case 'financial-office':
                        $contactSocialHealthFinancial[] = $c;
                        break;
                    default:
                        $contactUniversal[] = $c;
                        break;
                }
            }

            $seat = null;
            if($client->getSeat() instanceof Address)
                $seat = AddressExtFacade::addressToArray($client->getSeat(), $maxLevel, $level +1);

            $arr = array(
                'id' => $client->id,
                'version' => $client->version,
                'created' => $client->created->getTimestamp(),
                'modified' => $client->modified->getTimestamp(),
                'isDeleted' => $client->getIsDeleted(),

                'needsChecking' => $needsChecking,

                'type' => $client->getType(),
                'name' => $client->getName(),
                'identificationNumber' => $client->getIdentificationNumber(),
                'ico' => $client->getIdentificationNumber(),
                'dic' => $client->getDic(),
                'dphPeriodicity' => $client->getDphPeriodicity(),
                'frequencyOfBilling' => $client->getFrequencyOfBilling(),
                'typeOfBilling' => $client->getTypeOfBilling(),
                'status' => $client->getStatus(),
                'seat' => $seat,
                'managers' => array_map(function($contact)use($maxLevel, $level){
                    return ContactExtFacade::contactToArray($contact, $maxLevel, $level +1);
                }, $client->getManagers()->toArray()),
                'contacts' => $contacts,
                'maintainers' => array_map(function($employee)use($maxLevel, $level){
                    return EmployeeExtFacade::employeeToArray($employee, $maxLevel, $level +1);
                }, $client->getMaintainers()->toArray()),
                'repairPeriod' => $client->getRepairPeriod(),
                'workPlace' => $client->getWorkPlace(),
                'marketYear' => $client->getMarketYear(),
                'documentsTransferType' => $client->getDocumentsTransferType(),
                'accountingServices' => $client->getAccountingServices(),
                'salaryServices' => $client->getSalaryServices(),
                'strictlyQuarterlyBilling' => $client->getStrictlyQuarterlyBilling(),
                'formerNames' => $client->getFormerNames(),
                'activeClient' => $client->getActiveClient(),
                'companiesGroup' => $client->getCompaniesGroup(),
                'cooperationInitiated' => $client->getCooperationInitiated(),
                'cooperationTerminated' => $client->getCooperationTerminated(),
                'servicesProvidingSince' => "",
                'servicesProvidingUntil' => "",
                'mainAccountant' => $mainAccountant,
                'financialAccountant' => $financialAccountant,
                'payrollAccountant' => $payrollAccountant,
                'accountingSoftware' => $client->getAccountingSoftware(),
                'processingType' => $client->getProcessingType(),
                'roadTaxPayment' => $client->getRoadTaxPayment(),
                'salaryPayOffDay' => $client->getSalaryPayOffDay(),
                'vatDepositPeriod' => $client->getVatDepositPeriod(),
                'billingBy' => $client->getBillingBy(),
                'note' => $client->getNote(),
                'contract' => $client->getContract(),
                'roadTaxNumberOfCars' => $client->getRoadTaxNumberOfCars(),
                'taxDeferral' => $client->getTaxDeferral(),
                'externalDiskPath' => $client->getExternalDiskPath(),
                'contactBusiness' => $contactBusiness,
                'contactSocialHealthFinancial' => $contactSocialHealthFinancial,
                'contactUniversal' => $contactUniversal,
                'contactOrganization' => $contactOrganization,
                'dataAccessFee' => $client->getDataAccessFee(),
                'dataAccessFeePaid' => $client->getDataAccessFeePaid(),
            );
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
                $getter = "get".ucfirst($fieldName);
                $gd = $client->$getter();
                if (!empty($gd) && $gd->getTimestamp() > 0)
                    $arr[$fieldName] = $gd->getTimestamp(); //format("Y-m-d");
                else
                    $arr[$fieldName] = 0;
            }


            array_pop($list);
            return $arr;
        }
    }

    public function readTRClients($args){
		bf(func_get_args());
		if(isset($args->sort)){
			$sort = $args->sort;
		}else{
			$sort = array();
		}
		$collection = $this->findClients($args->limit, $args->start, $sort);
		$array = array();
		foreach($collection as $client){
			$array[] = array(
				'id' => $client->getId(),
				'name' => $client->getName(),
				'typeOfBilling' => $client->getTypeOfBilling(),
				'frequencyOfBilling' => $client->getFrequencyOfBilling(),
				'dphPeriodicity' => $client->getDphPeriodicity(),
				'taxDeferral' => $client->getTaxDeferral(),
				'repairPeriod' => $client->getRepairPeriod()
			);
		}
		return new Response(array('items' => $array, 'total' => $this->countClients()), 'Successfully read');
	}

    public function setContact($data){
	    bf(func_get_args());

	    $data = (object)$data;
	    $client = null;

	    if(isset($data->contactId) && isset($data->id))
	    {
            $client = $this->getModel('client')->findClient($data->id);
            $contact = $this->getModel('contact')->findContact($data->contactId);


	        /* #PeSp: not needed, see Ticket #107
            if($contact->getType() == 'company-manager')
            {
                $coll = $client->getManagers();
                if(!$coll->contains($contact))
                    $client->addManager($contact);
            }
            else if($contact->getType() == 'company-contact')
            {
                $coll = $client->getContacts();
                if(!$coll->contains($contact))
                    $client->addContact($contact);
            }
            else
                ;
	        */

            $coll = $client->getContacts();
            if(!$coll->contains($contact))
                $client->addContact($contact);

            $this->getModel('client')->save($client);

	    }
	    else
	       throw ClientModelException::canNotUpdateClient();

	    return new Response(static::clientToArray($client), 'Úpravy záznamu klienta byly úspěšně uloženy.');
	}


    public function update($data){
		bf(func_get_args());


        if(isset($data->needsChecking))
            unset($data->needsChecking);

        if(intval($data->id) == -1)
		{
			$seatData = (isset($data->seat))?($data->seat):(array());
			$managersData = (isset($data->managers))?($data->managers):(array());
			$contactsData = (isset($data->contacts))?($data->contacts):(array());
			$maintainersData = (isset($data->maintainers))?($data->maintainers):(array());

			unset($data->seat);
			unset($data->managers);
			unset($data->contacts);
			unset($data->maintainers);

            return $this->createClient((array)$data, (array)$seatData, (array)$managersData, (array)$contactsData, (array)$maintainersData);
		}
		else
			$client = $this->updateClient($data);

		return new Response(static::clientToArray($client), 'Úpravy záznamu klienta byly úspěšně uloženy.');
	}

    public function destroy($data){
		if(!is_array($data)){
			$data = array($data);
		}
		foreach($data as $d){
			$d = (array) $d;
			unset($d['seat']);
			unset($d['managers']);
			unset($d['contacts']);
			unset($d['maintainers']);
			$this->deleteClient($d['id']);
		}
		return new Response(true, 'Vybrané záznamy byly úspěšně smazány.');
	}

	protected function getDefaultSorting(){
		return (object) array('property' => 'name', 'direction' => 'ASC');
	}

	public function exportExcel($data){
        set_time_limit(300);
        $name = 'Klienti';
        $columnsIndexes = array();
        $disabled = array();
        $employeesColumns = array('maintainers');
        $employeeColumns = array('financialAccountant', 'payrollAccountant');
        $boolenColumns = array('salaryServices');
        $contactColumns = array('contacts', 'contactBusiness', 'contactSocialHealthFinancial', 'contactUniversal', 'contactOrganization');

        $doc = new \PHPExcel();
        $titles = array();
        $arrayItems = array();

        try {
            $ids = (isset($data->ids))?($data->ids):(array());
            $columns = (isset($data->columns))?($data->columns):(array());
            $clients = $this->findClients(null, null, array(), array(), $ids);
            $this->sortCollectionByIds($clients, $ids);
          
            foreach($columns as $column) {
                if(!in_array($column->field, $disabled)){
                    $titles[] = $column->title;
                    $columnsIndexes[] = $column->field;
                }
            }
            foreach($clients as $client) {
                $type = $client->getTypeTranslation();
                $dphPeriodicity = $client->getDphPeriodicityTranslation();
                $typeOfBilling = $client->getTypeOfBillingTranslation();
                $frequencyOfBilling = $client->getFrequencyOfBillingTranslation();
                $statusClient = $client->getStatusClientTranslation();
                $accountingServices = $client->getAccountingServicesTranslation();
                $clientProcessingType = $client->getClientProcessingTypeTranslation();
                $workPlace = $client->getWorkPlaceTranslation();
                $clientContract = $client->getClientContractTranslation();
                $client = static::clientToArray($client);
                $arrayClient = array();

                foreach($columnsIndexes as $column) {
                    $value = $client[$column];


                    if($column == 'seat') {
                        $value = $value['street']. ',' . $value['city'];
                    } else if($column == 'type') {
                        $value = $type;
                    } else if ($column == 'dphPeriodicity') {
                        $value = $dphPeriodicity;
                    } else if ($column == 'typeOfBilling') {
                        $value = $typeOfBilling;
                    } else if ($column == 'frequencyOfBilling') {
                        $value = $frequencyOfBilling;
                    } else if ($column == 'accountingServices') {
                        $value = $accountingServices;
                    } else if ($column == 'status') {
                        $value = $statusClient;
                    } else if ($column == 'contract') {
                        $value = $clientContract;
                    } else if ($column == 'workPlace') {
                        $value = $workPlace;
                    } else if ($column == 'processingType') {
                        $value = $clientProcessingType;
                    } else if ($column == 'billingBy') {
                        $value = str_replace("_"," ",$value);
                    } else if ($column == 'modified') {
                        $value = $dt = date('m/d/Y H:i:s',$value);
                    } else if ($column == 'created') {
                        $value = $dt = date('m/d/Y H:i:s',$value);
                    } else if ($column == 'needsChecking') {
                        if($value == TRUE){
                            $value = "Ano";
                        }else{
                            $value = "Ne";
                        }
                    } else if (in_array($column, $boolenColumns)) {
                        $boolen = array();
                        foreach($value as $bool) {
                            $boolen[$bool] = getBoolTranslation($bool);
                        }
                        $value = implode(', ', $boolen);
                    }  else if (in_array($column, $contactColumns)) {
                        $contacts = array();
                        foreach($value as $contact) {
                            $contacts[] = $contact['firstName'].' '.$contact['lastName'];
                        }
                        $value = implode(', ', $contacts);
                    } else if (in_array($column, $employeesColumns)) {
                        $employees = array();
                        if($value) {
                            foreach($value as $employee) {
                                $employees[] = $employee['name'];
                            }
                        }
                        $value = implode(', ', $employees);
                    } else if (in_array($column, $employeeColumns)) {
                        if($value) {
                            $value = $value['name'];
                        }
                    }
                    $arrayClient[$column] = $value;
                }
                $arrayItems[] = $arrayClient;
            }

            $list = $doc->getActiveSheet();
            $list->setTitle($name);
            $list->fromArray($titles, NULL, 'A1');
            $list->fromArray($arrayItems, NULL, 'A2');

            $hash = Strings::random(40, 'a-z0-9');
            $filename = Strings::webalize($name) . '-' . $hash . '.xlsx';
            $pathname = $this->exportDir . '/' . $filename;

            $writer = new \PHPExcel_Writer_Excel2007($doc);
            $writer->save($pathname);

            return new Response(array("hash"=>$hash), 'Export dat proběhl úspěšně.');
        } catch(\Exception $e){
            Debugger::log($e);
            bf($e);
            if($e instanceof Exception){
                throw $e;
            }else{
                throw ClientModelException::canNotCreateExport();
            }
        }
    }

    /**
     * Resort collection of objects by array of ids
     *
     * @param $collection
     * @param $ids
     */
    private function sortCollectionByIds(&$collection, $ids) {
        usort($collection, function($a, $b) use ($ids){
            $valA = array_search($a->getId(), $ids);
            $valB = array_search($b->getId(), $ids);

            if ($valA === false)
                return -1;
            if ($valB === false)
                return 0;

            if ($valA > $valB)
                return 1;
            if ($valA < $valB)
                return -1;
            return 0;
        });
    }

	//public function loadClientData($ico){
	public function loadClientData($data){
		//bf($ico);
		bf($data);
		//$result = parent::loadClientData($ico);
		$result = parent::loadClientData($data->ico);
		return new Response($result, 'Informace o klientovi byly úspěšně načteny ze serveru wwwinfo.mfcr.cz/ares');
	}
	
	public function createClient($clientData, $seatData, $managersData, $contactsData, $maintainersData){
		$return = parent::createClient((array) $clientData, (array) $seatData, (array) $managersData, (array) $contactsData, (array) $maintainersData);
		return new Response(static::clientToArray($return), "Klient '" . $clientData['name'] . "' byl úsěšně vytvořen.");
	}


    public function invoices($data) {
        $invoicesArray = array();
        $clientId= $data->id;

        $client = $this->getModel('client')->findClient($clientId);
        $invoices = $this->getModel('invoice')->findInvoicesByClient($client);

        foreach($invoices as $invoice)
            $invoicesArray[] = $invoice->toArray();

        return new Response(array('items' => $invoicesArray, 'total' => count($invoicesArray)), 'Successfully read');
    }

    private function processEmployee($m) {
        $m_user = $this->getModel('user')->createUser($m->user->username, 'fakePasssowrd', $m->user->email, array(), $m->user->accountState ,true);
        $m_address = $this->getModel('address')->findAddress($m->contact->address);
        $m_contact = $this->getModel('contact')->createContact($m_address, $m->contact->firstName, $m->contact->lastName, (array) $m->contact, true);

        $empl = $this->getModel('employee')->createEmployee($m_user, $m_contact, $m->codeAlfa, (array)$m, null, true);
        $m_user->onCreate();
        $m_contact->onCreate();
        $empl->onCreate();
        return $empl;
    }

    private function processContact($c) {
        $c_country = $this->getModel('country')->findCountryById($c->address->country);
        $c_address = $this->getModel('address')->createAddress($c_country, $c->address->city, $c->address->postalCode, $c->address->street, true);
        $contact = $this->getModel('contact')->createContact($c_address, $c->firstName, $c->lastName, (array) $c, true);
        $c_address->onCreate();
        $contact->onCreate();
        return $contact;
    }

    public function history($args) {
        $response = parent::history($args);
        foreach($response->result['items'] as $key => $complexItem) {
            $item = (object) $complexItem['entityJson_decoded'];
            $country = $this->getModel('country')->findCountryById($item->seat->country->id);
            $address = $this->getModel('address')->createAddress($country, $item->seat->city, $item->seat->postalCode, $item->seat->street, true);

            $contacts = array();
            foreach($item->contacts as $c) {
                $contacts[] = $this->processContact($c);
            }

            $managers = array();
            foreach($item->managers as $m) {
                $managers[] = $this->processContact($m);
            }

            $maintainers = array();
            foreach($item->maintainers as $m) {
                $maintainers[] = $this->processEmployee($m);
            }

            if($item->financialAccountant)
                $item->financialAccountant = $this->processEmployee($item->financialAccountant);

            if($item->payrollAccountant)
                $item->payrollAccountant = $this->processEmployee($item->payrollAccountant);

            $client = $this->getModel('client')->createClient($item->type, $item->identificationNumber, $item->name,
                $address, (array) $item, $managers, $contacts, $maintainers, true);

            $address->onCreate();
            $client->onCreate();

            $response->result['items'][$key]['entityJson_decoded'] = static::clientToArray($client);

        }
        return $response;
    }

    public function exportExcelInvoiceItems($data){
        $ignoreOtherInvoices = (isset($data->ignoreOtherInvoices))?(($data->ignoreOtherInvoices == 'true')?true:false):false;
        $items = $this->loadInvoiceItems($data->id, $data->year, $data->month, $data->date, $ignoreOtherInvoices);
        $hash = $this->exportInvoiceItems($items);
        return new Response(array("hash"=>$hash), 'Export položek proběhl úspěšně.');
    }

    private function exportInvoiceItems($itemCategories) {
        try{
            $doc = new \PHPExcel();
            $index = 1;
            $titles = array('Datum', 'Číslo', 'Text', 'Cena');
            foreach($itemCategories as $itemCategoryName => $items) {
                $name = Invoicer::$categoryNames[$itemCategoryName];
                $list = $doc->createSheet($index);
                $list->setTitle($name);
                $arrayItems = array($name);
                foreach($items as $item) {
                    $arrayItems[] = (array)$item;
                }
                $list->setCellValue('A1', $name);
                $list->fromArray($titles, NULL, 'A3');
                $list->fromArray($arrayItems, NULL, 'A3');
                $index++;
            }
            if(!empty($itemCategories)) $doc->removeSheetByIndex(0);
            $hash = Strings::random(40, 'a-z0-9');
            $filename = Strings::webalize('test') . '-' . $hash . '.xlsx';
            $pathname = $this->exportDir . '/' . $filename;

            $writer = new \PHPExcel_Writer_Excel2007($doc);
            $writer->save($pathname);
            return $hash;
        }catch(\Exception $e){
            Debugger::log($e);
            bf($e);
            if($e instanceof Exception){
                throw $e;
            }else{
                throw TimeRecordModelException::canNotCreateExport();
            }
        }
    }


    private function loadInvoiceItems($id, $year, $month, $fakeNow, $ignoreOtherInvoices) {

        /*
         * First check if the client really exists
         */
        $client = $this->getModel('client')->findClient($id);
        if(!$client instanceof Client){
            throw ClientModelException::canNotFindClients();
        }

        $invoicer = new Invoicer($client, $this->context, $ignoreOtherInvoices, $year, false, $month);
        $invoice_data = $invoicer->calculateInvoice($year, $month, $fakeNow);
        if($this->isInvoiceWithoutError($invoice_data))
        {
            $items = $invoice_data['rawItems'];
        } else {
            $items = $invoice_data;
        }

        return $items;
    }

    public function priceByStrId($args) {
        $price = $this->getModel('price')->findPriceByStrId($args->priceStrId);
        $priceArray = $this->getModel('price')->itemToArray($price);
        return new Response($priceArray, 'Successfully read');

    }
}





