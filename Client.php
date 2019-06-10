<?php

namespace App\ClientModule\Model;

use Nette\Utils\Validators,
	Nette\Utils\Strings;
use Orakulum\Model\AbstractEntity;
use Doctrine\ORM\Mapping as ORM,
	Doctrine\Common\Collections\ArrayCollection;

use App\CommonModule\Model as Common,
	App\EmployeeModule\Model\Employee;

/**
 * Description of UserEntity
 *
 * @ORM\Entity(repositoryClass="App\ClientModule\Model\ClientRepository")
 * @ORM\Table(name="clients")
 * @author JG
 */
class Client extends AbstractEntity{

	const TRADESMAN = 'tradesman';
	const COMPANY = 'company';
	const PERSON = 'person';

    const FREQUENCY_GRATIS = 0;
	const FREQUENCY_NONE = -1;
    const FREQUENCY_SINGLETIME = -2;
	const FREQUENCY_MONTH = 1;
	const FREQUENCY_QUARTER = 3;
	const FREQUENCY_YEAR = 12;

    const BILLING_SINGLETIME = 'single_time';
	const BILLING_FEE = 'fee';
	const BILLING_ITEMS = 'items';
	const BILLING_HOURS = 'hours';
    const BILLING_GRATIS = 'gratis';


    const RATE_LOWER = 1;
	const RATE_HIGHER = 2;
	
	const STATUS_ACTIVE = 'active';
	const STATUS_OLD = 'old';
	
	const PATTERN_REPAIR_PERIOD = '/^(((\d{1,2}|\d{1}Q)\/)?(20\d{2}))?-(((\d{1,2}|\d{1}Q)\/)?(20\d{2}))?$/i';
	
	
	//#JG: Ticket #7 start
	
	const PROVIDER = 'provider';
	const CLIENT = 'client';
	const UNDEFINED = 'undefined';
	
	const TO_PROVIDER = 'to_provider';
	const FROM_CLIENT = 'from_client';
	const ELECTRONIC = 'electronic';
    const CLIENT_TO_PEKLO = 'client_to_peklo';

	//const UNDEFINED = 'undefined';

    const BILLING = 'billing';
    const CONTROLS = 'controls';
    const BILLING_CONTROLS = 'billing_controls';
    const SINGLE_TIME = 'single_time';
	const BILLING_OTHER = 'billing_other';
	const HOUR_RATE = 'hour_rate';

	const POHODA = 'pohoda';
	const HELIOS_RED = 'helios_red';
	const HELIOS_ORANGE = 'helios_orange';
	const MONEY = 'money';
	const MICRONET = 'micronet';
	const PAMICA = 'pamica';

    const SINGLETIME = 'single_time';
	const ACCOUNTING = 'accounting';
	const ACCOUNTING_NONPROFITS = 'accounting_nonprofits';
	const TAX_REGISTER = 'tax_register';
	const NONE = 'none';
	const OTHER = 'other';


	//const NONE = 'none';
	const MIDYEAR = 'midyear';
	const QUARTERLY = 'quarterly';
	const MONTHLY = 'monthly';
	
	const PEKLO_II_SRO = 'peklo_ii_sro';
	const PRVNI_MALESICKA_UCETNI = 'prvni_malesicka_ucetni';
    const RAJ_UCETNICH_SLUZEB = 'raj_ucetnich_sluzeb';
    const TOMAS_KLOUCEK = 'tomas_kloucek';
    const GRATIS = 'gratis';
    const ERUDICA = 'erudica';


    //#JG: Ticket #7 end

    //#JG: Ticket #107 start

    const YES = 'yes';
    const NO = 'no';
    const READY_TO_SIGN = 'ready_to_sign';

    //#JG: Ticket #107 end
	/**
	 * @ORM\Column(type="string", length=12)
	 * @var type 
	 */
	private $type;
	
	/**
	 * @ORM\Column(type="string", length=160)
	 * @var type 
	 */
	private $name;
	
	/**
	 * Ičo (nebo Rodné číslo)
	 * @ORM\Column(type="string", length=12)
     * @var type
	 */
	private $identificationNumber = '';
	
	/**
	 * @ORM\Column(type="string", length=12)
	 * @var type 
	 */
	private $dic = '';
	
	/**
	 * @ORM\Column(type="integer")
	 */
	private $dphPeriodicity = 0;
	
	/**
	 * @ORM\Column(type="integer")
	 */
	private $frequencyOfBilling = 0;


	/**
	 * @ORM\Column(type="string", length=20);
	 * @var type 
	 */
	private $typeOfBilling = 'hours';

	/**
	 * #300 - limit mezd pro kvartální fakturaci
	 * @ORM\Column(type="integer")
	 */
	private $wagesQuarterLimit = 1;
	
	/**
	 * @ORM\OneToOne(targetEntity="App\CommonModule\Model\Address")
	 */
	private $seat;
	
	/**
	 * @ORM\ManyToMany(targetEntity="App\CommonModule\Model\Contact", cascade={"remove"})
	 * @ORM\JoinTable(name="client_managers_contact")
	 * @var type
     * TODO: this can be removed, everything will be stored in contacts, see #107
	 */
	private $managers;
	
	/**
	 * JG: kontaktni osoby
     * Ticket #107: this stores all contacts.
	 * @ORM\ManyToMany(targetEntity="App\CommonModule\Model\Contact")
	 * @ORM\JoinTable(name="client_contacts_contact")
	 * @var type 
	 */
	private $contacts;

	/**
	 * JG: zamestnanci zodpovedni za daneho klienta
	 * @ORM\ManyToMany(targetEntity="App\EmployeeModule\Model\Employee", mappedBy="clients")
	 * @var type
     * TODO: this can be removed, everything will be stored in contacts, see #107
	 */
	private $maintainers;
	
	/**
	 * @ORM\Column(type="string", length=20)
	 * @var type 
	 */
	private $status = 'active';
	
	/**
	 * @ORM\Column(type="boolean", nullable=true)
	 * @var type 
	 */	
	private $taxDeferral;
	
	
	/**
	 * @ORM\Column(type="string", length=15, nullable=true) 
	 * @var string 
	 */
	private $repairPeriod; 
	
	//#JG: Ticket #5 start
	/**
	 * JG: kontakt na statutarni organ
	 * @ORM\ManyToOne(targetEntity="App\CommonModule\Model\Contact")
	 * @var type
     * TODO: this can be removed, everything will be stored in contacts, see #107
	 */
	private $organization;
	
	//#JG: Ticket #5 end
	
	//#JG: Ticket #7 start
	
	/**
	 *#JG: místo výkonu činnost (kde se zpracovává účetnictví)
	 * @ORM\Column(type="string", nullable=true) 
	 * @var type
	 */
	private $workPlace;
	
	/**
	 *#JG: předávání dokladů
	 * @ORM\Column(type="string", nullable=true) 
	 * @var type
	 */
	private $documentsTransferType;
	
	/**
	 *#JG: účetní služby
	 * @ORM\Column(type="string", nullable=true) 
	 * @var type
	 */
	private $accountingServices;
	
	/**
	 *#JG: mzdové služby
	 * @ORM\Column(type="boolean", nullable=true)
	 * @var type
	 */
	private $salaryServices;

	
	/**
	 * #299 - čistě kvartální fakturace
	 * @ORM\Column(type="boolean") 
	 */
	private $strictlyQuarterlyBilling = false;
	
	/**
	 *#JG: dřívější názvy
	 * @ORM\Column(type="string", nullable=true) 
	 * @var string
	 */
	private $formerNames;
	
	/**
	 *#JG: aktivní klient
	 * @ORM\Column(type="boolean", nullable=true) 
	 * @var type
	 */
	private $activeClient;

	/**
	 *#JG: Hospodářský rok
	 * @ORM\Column(type="integer")
	 */
	private $marketYear;

	/**
	 *#JG: skupina firem
	 * @ORM\Column(type="text", nullable=true) 
	 * @var text
	 */
	private $companiesGroup;

	/**
	 * Zahajeni spoluprace
	 * @ORM\Column(type="datetime", nullable=true)
	 * @var datetime
	 */
	private $cooperationInitiated;

	/**
	 * Ukonceni spoluprace
	 * @ORM\Column(type="datetime", nullable=true)
	 * @var datetime
	 */
	private $cooperationTerminated;

	/**
	 *#JG: poskytování služeb od
	 * @ORM\Column(type="datetime", nullable=true) 
	 * @var datetime
	 */
	private $servicesProvidingSince;
	
	/**
	 *#JG: poskytování služeb do
	 * @ORM\Column(type="datetime", nullable=true) 
	 * @var datetime
	 */
	private $servicesProvidingUntil;
	
	/**
	 *#JG: finanční účetní
	 * @ORM\ManyToOne(targetEntity="App\EmployeeModule\Model\Employee", fetch="EAGER")
	 * @var type
     * TODO: this can be removed, see #107
	 */
	private $mainAccountant;
	
	/**
	 *#JG: hlavní účetní
	 * @ORM\ManyToOne(targetEntity="App\EmployeeModule\Model\Employee", fetch="EAGER")
	 * @var type
	 */
	private $financialAccountant;
	
	/**
	 *#JG: mzdová účetní
	 * @ORM\ManyToOne(targetEntity="App\EmployeeModule\Model\Employee", fetch="EAGER")
	 * @var type
	 */
	private $payrollAccountant;
	
	/**
	 *#JG: účetní software
	 * @ORM\Column(type="string", nullable=true) 
	 * @var type
	 */
	private $accountingSoftware;
	
	/**
	 *#JG: zpracovávání
	 * @ORM\Column(type="string", nullable=true) 
	 * @var type
	 */
	private $processingType;
	
	/**
	 *#JG: plátcovství silniční daně
	 * @ORM\Column(type="boolean", nullable=true)
	 * @var type
	 */
	private $roadTaxPayment;
	
	/**
	 *#JG: výplatní termín mezd
	 * @ORM\Column(type="integer", nullable=true) 
	 * @var type
	 */
	private $salaryPayOffDay;
	
	/**
	 *#JG: zálohy na daň z příjmu
	 * @ORM\Column(type="string", nullable=true) 
	 * @var type
	 */
	private $vatDepositPeriod;
	
	/**
	 *#JG: fakturace od subjektu
	 * @ORM\Column(type="string", nullable=true) 
	 * @var type
	 */
	private $billingBy;
	
	//#JG: Ticket #7 end

    //#JG: Ticket #107 start

    /**
     *#JG: smlouva
     * @ORM\Column(type="string", length=50, nullable=true)
     * @var type
     */
    private $contract;

    /**
     *#JG: poznámka
     * @ORM\Column(type="string", length=255, nullable=true)
     * @var string
     */
    private $note;

    /**
     *#JG: datum předání SD klientovi
     * @ORM\Column(type="datetime", nullable=true)
     * @var datetime
     */
    private $roadTaxDeclarationDeliveryDate;

    /**
     *#JG: počet aut v SD
     * @ORM\Column(type="integer", nullable=true)
     * @var type
     */
    private $roadTaxNumberOfCars;

    /**
     *#JG: datum předání DP
     * @ORM\Column(type="datetime", nullable=true)
     * @var datetime
     */
    private $taxesDeclarationDeliveryDate;

    /**
     *#JG: datum předání podkladů pro DP
     * @ORM\Column(type="datetime", nullable=true)
     * @var datetime
     */
    private $taxesDocumentsDeliveryDate;

    //#JG: Ticket #107 end

    /**
     * @ORM\Column(type="string", length=255);
     * @var type
     */
    private $externalDiskPath;

    /**
     * @ORM\Column(type="integer", length=11);
     * @var integer
     */
    private $pohodaID;

    /**
     * Have client one time data access fee?
     * @ORM\Column(type="boolean")
     * @var integer
     */
    private $dataAccessFee = 0;

    /**
     * Is data access fee paid?
     * @ORM\Column(type="boolean")
     * @var integer
     */
    private $dataAccessFeePaid = 0;


	public function __construct($type, $ico, $name, Common\Address $seat, array $data=array(), array $managers = array(), array $contacts = array(), array $maintainers = array()){
		$this->maintainers = new ArrayCollection();
		$this->contacts = new ArrayCollection();
		$this->managers = new ArrayCollection();
		$this->setType($type);
		$this->setName($name);
        $this->setIdentificationNumber($ico);
		$this->setSeat($seat);

        if(!empty($data['organization']))
		    $this->setOrganization($data['organization']);
		
		
		$this->_setCustomData($data, array('maintainers' , 'organization',  'contacts', 'managers', 'type', 'name', 'identificationNumber', 'seat'));

		foreach($managers as $manager){
			$this->addManager($manager);
		}
		
		foreach($contacts as $contact){
			$this->addContact($contact);
		}
		
		foreach($maintainers as $maintainer){
			$this->addMaintainer($maintainer);
		}
		
	}
	
	public function setType($type){
		$this->type = static::validateType($type);
		return $this;
	}
	
	public function setName($name){
		$this->name= static::validateName($name);
		return $this;
	}
	
	public function setIdentificationNumber($ico){
		$this->identificationNumber = static::validateIdentificationNumber($ico);
		return $this;
	}
	
	public function setDic($dic){
		$this->dic = static::validateDic($dic);
		return $this;
	}
	
	public function setDphPeriodicity($dph){
		$this->dphPeriodicity = static::validateDphPeriodicity($dph);
		return $this;
	}
	
	public function setFrequencyOfBilling($freq){
		$this->frequencyOfBilling = static::validateFrequencyOfBilling($freq);
		return $this;
	}
	
	public function setTypeOfBilling($type){
		$this->typeOfBilling = static::validateTypeOfBilling($type);
		return $this;
	}
	
	public function setStrictlyQuarterlyBilling($billing){
		$this->strictlyQuarterlyBilling = static::validateStrictlyQuarterlyBilling($billing);
		return $this;
	}
	
	public function setWagesQuarterLimit($limit){
		$this->wagesQuarterLimit = static::validateWagesQuarterLimit($limit);
		return $this;
	}
	
	public function setSeat(Common\Address $seat){
		$this->seat = $seat;
		return $this;
	}
		
	public function addManager(Common\Contact $manager){
		$this->managers->add($manager);
		return $this;
	}
	
	public function removeManager(Common\Contact $manager){
		if($this->managers->contains($manager))
			$this->managers->removeElement($manager);
		return $this;
	}
	
	public function addContact(Common\Contact $contact){
		$this->contacts->add($contact);
		return $this;
	}
	
	public function removeContact(Common\Contact $contact){
		if($this->contacts->contains($contact))
			$this->contacts->removeElement($contact);
		return $this;
	}
	
	public function addMaintainer(Employee $maintainer, $makeAdditionAlsoOnTheOtherSide = true){
		$this->maintainers->add($maintainer);
		
		if($makeAdditionAlsoOnTheOtherSide){
			$maintainer->addClient($this, false);
		}
		return $this;
	}
	
	public function removeMaintainer(Employee $maintainer, $makeAdditionAlsoOnTheOtherSide = true){
		if($this->maintainers->contains($maintainer))
			$this->maintainers->removeElement($maintainer);
		
		if($makeAdditionAlsoOnTheOtherSide){
			$maintainer->removeClient($this, false);
		}
		
		return $this;
	}
	
	public function setStatus($status){
		$this->status = static::validateStatus($status);
		return $this;
	}
	
	public function setTaxDeferral($taxDeferral){
		$this->taxDeferral = static::validateTaxDeferral($taxDeferral);
		return $this;
	}
	
	public function setRepairPeriod($repairs){
		$this->repairPeriod = static::validateRepairPeriod($repairs);
		return $this;
	}
	
	//#JG: Ticket #5 start
	public function setOrganization(Common\Contact $contact){
		$this->organization = $contact;
		return $this;
	}
	//#JG: Ticket #5 end
	
	//#JG: Ticket #7 start
	
	public function setWorkPlace(/*string*/ $workPlace){
		$this->workPlace = static::validateWorkPlace($workPlace);
		return $this;
	}
	
	public function setDocumentsTransferType(/*string*/ $documentsTransferType){
		$this->documentsTransferType = static::validateDocumentsTransferType($documentsTransferType);
		return $this;
	}
	
	public function setAccountingServices(/*string*/ $accountingServices){
		$this->accountingServices = static::validateAccountingServices($accountingServices);
		return $this;
	}
	
	public function setSalaryServices(/*boolean*/ $salaryServices){
		$this->salaryServices = static::validateSalaryServices($salaryServices);
		return $this;
	}
	
	public function setFormerNames(/*string*/ $formerNames){
		$this->formerNames = static::validateFormerNames($formerNames);
		return $this;
	}
	
	public function setActiveClient(/*boolean*/ $activeClient){
        $this->activeClient = static::validateActiveClient($activeClient);
		return $this;
	}
	
	public function setCompaniesGroup(/*text*/ $companiesGroup){
		$this->companiesGroup = static::validateCompaniesGroup($companiesGroup);
		return $this;
	}

	public function setCooperationInitiated(/*datetime*/ $cooperationInitiated)
	{
		$this->cooperationInitiated = static::validateCooperationInitiated($cooperationInitiated);
		return $this;
	}

	public function setCooperationTerminated(/*datetime*/ $cooperationTerminated)
	{
		$this->cooperationTerminated = static::validateCooperationTerminated($cooperationTerminated);
		return $this;
	}

	
	public function setServicesProvidingSince(/*datetime*/ $servicesProvidingSince){
		$this->servicesProvidingSince = static::validateServicesProvidingSince($servicesProvidingSince);
		return $this;
	}
	
	public function setServicesProvidingUntil(/*datetime*/ $servicesProvidingUntil){
		$this->servicesProvidingUntil = static::validateServicesProvidingUntil($servicesProvidingUntil);
		return $this;
	}
	
	public function setMainAccountant(/*OneToOne*/ $mainAccountant){
		$this->mainAccountant = $mainAccountant;
		return $this;
	}
	
	public function setFinancialAccountant(/*OneToOne*/ $financialAccountant){
		$this->financialAccountant = $financialAccountant;
		return $this;
	}
	
	public function setPayrollAccountant(/*OneToOne*/ $payrollAccountant){
		$this->payrollAccountant = $payrollAccountant;
		return $this;
	}
	
	public function setAccountingSoftware(/*string*/ $accountingSoftware){
		$this->accountingSoftware = static::validateAccountingSoftware($accountingSoftware);
		return $this;
	}
	
	public function setProcessingType(/*string*/ $processingType){
		$this->processingType = static::validateProcessingType($processingType);
		return $this;
	}
	
	public function setRoadTaxPayment(/*boolean*/ $roadTaxPayment){
        $this->roadTaxPayment = static::validateRoadTaxPayment($roadTaxPayment);
		return $this;
	}
	
	public function setSalaryPayOffDay(/*integer*/ $salaryPayOffDay){
		$this->salaryPayOffDay = static::validateSalaryPayOffDay($salaryPayOffDay);
		return $this;
	}
	
	public function setVatDepositPeriod(/*string*/ $vatDepositPeriod){
		$this->vatDepositPeriod = static::validateVatDepositPeriod($vatDepositPeriod);
		return $this;
	}
	
	public function setBillingBy(/*string*/ $billingBy){
		$this->billingBy = static::validateBillingBy($billingBy);
		return $this;
	}
	
	//#JG: Ticket #7 end

    //#JG: Ticket #107 start

    public function setContract(/*string*/ $contract){
        $this->contract = static::validateContract($contract);
        return $this;
    }

    public function setNote(/*string*/ $note){
        $this->note = static::validateNote($note);
        return $this;
    }

    public function setRoadTaxDeclarationDeliveryDate(/*datetime*/ $roadTaxDeclarationDeliveryDate){
        $this->roadTaxDeclarationDeliveryDate = static::validateRoadTaxDeclarationDeliveryDate($roadTaxDeclarationDeliveryDate);
        return $this;
    }

    public function setRoadTaxNumberOfCars(/*integer*/ $roadTaxNumberOfCars){
        $this->roadTaxNumberOfCars = static::validateRoadTaxNumberOfCars($roadTaxNumberOfCars);
        return $this;
    }

    public function setTaxesDeclarationDeliveryDate(/*datetime*/ $taxesDeclarationDeliveryDate){
        $this->taxesDeclarationDeliveryDate = static::validateTaxesDeclarationDeliveryDate($taxesDeclarationDeliveryDate);
        return $this;
    }

    public function setTaxesDocumentsDeliveryDate(/*datetime*/ $taxesDocumentsDeliveryDate){
        $this->taxesDocumentsDeliveryDate = static::validateTaxesDocumentsDeliveryDate($taxesDocumentsDeliveryDate);
        return $this;
    }
    //#JG: Ticket #107 end
	
	public static function validateType($type){
		$allowed = array(static::TRADESMAN, static::COMPANY, static::PERSON);
		if(!in_array($type, $allowed)){
			throw ClientException::invalidType($type);
		}
		return $type;
	}
	
	public static function validateName($name){
		if(!Validators::is($name, 'string:1..160')){
			throw ClientException::invalidName($name);
		}
		return $name;
	}
	
	public static function validateIdentificationNumber($ico){
        if(empty($ico)) return "";

		if(!Validators::is($ico, 'string:8..11')){
			throw ClientException::invalidIdentificationNumber($ico);
		}

        if(strlen($ico)>8)
        {
            $normalizedIcoPartOne = substr($ico,0,6);
            $normalizedIcoPartTwo = substr($ico, 6);
            if(!is_numeric($normalizedIcoPartTwo))
                $normalizedIcoPartTwo = substr($normalizedIcoPartTwo,1);
            $ico = $normalizedIcoPartOne.'/'.$normalizedIcoPartTwo;
        }


		return $ico;
	}
	
	public static function validateDic($dic){
		if(!Validators::is($dic, 'string:10..14')){
			throw ClientException::invalidDic($dic);
		}
		return $dic;
	}
	
	public static function validateDphPeriodicity($dph){
		$dph = intval($dph);
		$allowed = array(static::FREQUENCY_NONE, static::FREQUENCY_MONTH, static::FREQUENCY_QUARTER, static::FREQUENCY_YEAR);
		if(!in_array($dph, $allowed)){
			throw ClientException::invalidDphPeriodicity($dph);
		}
		return $dph;
	}

	public static function validateFrequencyOfBilling($freq){
		$freq = intval($freq);
		$allowed = array(static::FREQUENCY_SINGLETIME, static::FREQUENCY_NONE, static::FREQUENCY_MONTH, static::FREQUENCY_QUARTER, static::FREQUENCY_YEAR, static::FREQUENCY_GRATIS);
		if(!in_array($freq, $allowed)){
			throw ClientException::invalidFrequencyOfBilling($freq);
		}
		return $freq;
	}

	public static function validateTypeOfBilling($type){
		$allowed = array(static::BILLING_SINGLETIME, static::BILLING_FEE, static::BILLING_ITEMS, static::BILLING_HOURS, static::BILLING_GRATIS);
		if(!in_array($type, $allowed)){
			throw ClientException::invalidTypeOfBilling($type);
		}
		return $type;
	}

	public static function validateStrictlyQuarterlyBilling($billing){
		$allowed = array(true, false);
		if(!in_array($billing, $allowed)){
			throw ClientException::invalidQuarterlyBilling();
		}
		return $billing;
	}

	public static function validateWagesQuarterLimit($limit){
		if ($limit < 0) {
			throw ClientException::invalidWagesQuarterLimit($limit);
		}
		return $limit;
	}
	
	public static function validateStatus($status){
		$allowed = array(static::STATUS_ACTIVE, static::STATUS_OLD);
		if(!in_array($status, $allowed)){
			throw ClientException::invalidStatus($status);
		}
		return $status;
	}
	
	public static function validateTaxDeferral($taxDeferral){
		$allowed = array(true, false, null);
		if(!in_array($taxDeferral, $allowed)){
			throw ClientException::invalidTaxDeferral();
		}
		return $taxDeferral;
	}
	
	public static function validateRepairPeriod($repairs){
        if(empty($repairs)) return null;

		if(!preg_match(static::PATTERN_REPAIR_PERIOD, $repairs)){
			throw ClientException::invalidRepairPeriod($repairs);
		}
		return $repairs;
	}
	
	//#JG: Ticket #7 start
	
	public static function validateWorkPlace(/*string*/ $workPlace){
        if(empty($workPlace)) return null;

		$allowed = array(static::PROVIDER, static::CLIENT, static::UNDEFINED, );
		if(!in_array($workPlace,$allowed)){
			throw ClientException::invalidWorkPlace($workPlace);
		}
		return $workPlace;
	}
	
	public static function validateDocumentsTransferType(/*string*/ $documentsTransferType){
        if(empty($documentsTransferType)) return null;

		$allowed = array(static::CLIENT_TO_PEKLO, static::TO_PROVIDER, static::FROM_CLIENT, static::ELECTRONIC, static::UNDEFINED, );
		if(!in_array($documentsTransferType,$allowed)){
			throw ClientException::invalidDocumentsTransferType($documentsTransferType);
		}
		return $documentsTransferType;
	}
	
	public static function validateAccountingServices(/*string*/ $accountingServices){
        if(empty($accountingServices)) return null;

		$allowed = array(static::BILLING,static::CONTROLS,static::BILLING_CONTROLS,static::SINGLE_TIME, static::BILLING_OTHER, static::HOUR_RATE );
		if(!in_array($accountingServices,$allowed)){
			throw ClientException::invalidAccountingServices($accountingServices);
		}
		return $accountingServices;
	}
	
	public static function validateSalaryServices(/*boolean*/ $salaryServices){
		return $salaryServices;
	}
	
	public static function validateFormerNames(/*string*/ $formerNames){
		return $formerNames;
	}
	
	public static function validateActiveClient(/*boolean*/ $activeClient){
		return $activeClient;
	}
	
	public static function validateCompaniesGroup(/*text*/ $companiesGroup){
		return $companiesGroup;
	}

	public static function validateCooperationInitiated(/*datetime*/ $cooperationInitiated) {
		return $cooperationInitiated;
	}

	public static function validateCooperationTerminated(/*datetime*/ $cooperationTerminated) {
		return $cooperationTerminated;
	}

	public static function validateServicesProvidingSince(/*datetime*/ $servicesProvidingSince){
		return $servicesProvidingSince;
	}
	
	public static function validateServicesProvidingUntil(/*datetime*/ $servicesProvidingUntil){
		return $servicesProvidingUntil;
	}
	
	public static function validateMainAccountant(/*OneToOne*/ $mainAccountant){
		return $mainAccountant;
	}
	
	public static function validateFinancialAccountant(/*OneToOne*/ $financialAccountant){
		return $financialAccountant;
	}
	
	public static function validatePayrollAccountant(/*OneToOne*/ $payrollAccountant){
		return $payrollAccountant;
	}
	
	public static function validateAccountingSoftware(/*string*/ $accountingSoftware){
        if(empty($accountingSoftware)) return null;

		$allowed = array(static::POHODA, static::HELIOS_RED, static::HELIOS_ORANGE, static::MONEY, static::MICRONET, static::PAMICA);
		if(!in_array($accountingSoftware,$allowed)){
			throw ClientException::invalidAccountingSoftware($accountingSoftware);
		}
		return $accountingSoftware;
	}
	
	public static function validateProcessingType(/*string*/ $processingType){
        if(empty($processingType)) return null;

		$allowed = array(static::SINGLETIME,static::ACCOUNTING, static::TAX_REGISTER, static::NONE, static::ACCOUNTING_NONPROFITS, static::OTHER);
		if(!in_array($processingType,$allowed)){
			throw ClientException::invalidProcessingType($processingType);
		}
		return $processingType;
	}
	
	public static function validateRoadTaxPayment(/*boolean*/ $roadTaxPayment){
		return $roadTaxPayment;
	}
	
	public static function validateSalaryPayOffDay(/*integer*/ $salaryPayOffDay){
        if(empty($salaryPayOffDay)) return null;

		$salaryPayOffDay = intval($salaryPayOffDay);
		if(!($salaryPayOffDay >= 1 && $salaryPayOffDay <= 31)){
			throw ClientException::invalidSalaryPayOffDay($salaryPayOffDay);
		}
		return $salaryPayOffDay;
	}
	
	public static function validateVatDepositPeriod(/*string*/ $vatDepositPeriod){
        if(empty($vatDepositPeriod)) return null;

		$allowed = array(static::NONE, static::MIDYEAR, static::QUARTERLY, static::MONTHLY, );
		if(!in_array($vatDepositPeriod,$allowed)){
			throw ClientException::invalidVatDepositPeriod($vatDepositPeriod);
		}
		return $vatDepositPeriod;
	}
	
	public static function validateBillingBy(/*string*/ $billingBy){
        if(empty($billingBy)) return null;

		$allowed = array(static::TOMAS_KLOUCEK, static::PEKLO_II_SRO, static::PRVNI_MALESICKA_UCETNI, static::RAJ_UCETNICH_SLUZEB, static::GRATIS, static::ERUDICA);
		if(!in_array($billingBy,$allowed)){
			throw ClientException::invalidBillingBy($billingBy);
		}
		return $billingBy;
	}

    //#JG: Ticket #7 end

    //#JG: Ticket #107 start

    public static function validateContract(/*string*/ $contract){
        if(empty($contract)) return null;

        $allowed = array(static::YES, static::NO, static::READY_TO_SIGN, );
        if(!in_array($contract,$allowed)){
            throw ClientException::invalidContract($contract);
        }
        return $contract;
    }

    public static function validateNote(/*string*/ $note){
        if(empty($note)) return null;

        if(!Validators::is($note, 'string:0..255')){
            throw ClientException::invalidNote($note);
        }
        return $note;
    }

    public static function validateRoadTaxDeclarationDeliveryDate(/*datetime*/ $roadTaxDeclarationDeliveryDate){
        return $roadTaxDeclarationDeliveryDate;
    }

    public static function validateRoadTaxNumberOfCars(/*integer*/ $roadTaxNumberOfCars){
        if(empty($roadTaxNumberOfCars)) return null;

        $roadTaxNumberOfCars = intval($roadTaxNumberOfCars);
        return $roadTaxNumberOfCars;
    }

    public static function validateTaxesDeclarationDeliveryDate(/*datetime*/ $taxesDeclarationDeliveryDate){
        return $taxesDeclarationDeliveryDate;
    }

    public static function validateTaxesDocumentsDeliveryDate(/*datetime*/ $taxesDocumentsDeliveryDate){
        return $taxesDocumentsDeliveryDate;
    }


    //#JG: Ticket #107 end


	/**
	 * @return int
	 */
	public function getMarketYear()
	{
		return $this->marketYear;
	}

	/**
	 * @param int $marketYear
	 */
	public function setMarketYear($marketYear)
	{
		$this->marketYear = $marketYear;
	}


	public function getTaxDeferral(){
		return $this->taxDeferral;
	}
	
	public function getRepairPeriod(){
		return $this->repairPeriod;
	}
	
	public function getType(){
		return $this->type;
	}
	
	public function getName(){
		return $this->name;
	}

	public function getIdentificationNumber(){
		return $this->identificationNumber;
	}
	
	public function getDphPeriodicity(){
		return $this->dphPeriodicity;
	}

	public function getFrequencyOfBilling(){
		return $this->frequencyOfBilling;
	}

	public function getTypeOfBilling(){
		return $this->typeOfBilling;
	}

	public function getTypeName(){
		return $this->type;
	}

	public function getStrictlyQuarterlyBilling(){
		return $this->strictlyQuarterlyBilling;
	}
	
	public function getWagesQuarterLimit(){
		return $this->wagesQuarterLimit;
	}
	
	public function getDic(){
		return $this->dic;
	}
	
	public function getSeat(){
		return $this->seat;
	}
	
	public function getManagers(){
		return $this->managers;
	}
	
	public function clearManagers(){
		$this->managers->clear();
		return $this->managers;
	}
	
	public function getContacts(){
		return $this->contacts;
	}
	
	public function clearContacts(){
		$this->contacts->clear();
		return $this->contacts;
	}
	
	public function getMaintainers(){
		return $this->maintainers;
	}
	
	public function clearMaintainers(){
		$this->maintainers->clear();
		return $this->maintainers;
	}
	
	public function getStatus(){
		return $this->status;
	}
	
	//#JG: Ticket #5 start
	public function getOrganization(){
		return $this->organization;
	}
	
	//#JG: Ticket #5 end
	
	//#JG: Ticket #7 start

	public function getWorkPlace(){return $this->workPlace;}
	public function getDocumentsTransferType(){return $this->documentsTransferType;}
	public function getAccountingServices(){return $this->accountingServices;}
	public function getSalaryServices(){return $this->salaryServices;}
	public function getFormerNames(){return $this->formerNames;}
	public function getActiveClient(){return $this->activeClient;}
	public function getCompaniesGroup(){return $this->companiesGroup;}
	public function getCooperationInitiated(){return $this->cooperationInitiated;}
	public function getCooperationTerminated(){return $this->cooperationTerminated;}
	public function getServicesProvidingSince(){return $this->servicesProvidingSince;}
	public function getServicesProvidingUntil(){return $this->servicesProvidingUntil;}
	public function getMainAccountant(){return $this->mainAccountant;}
	public function getFinancialAccountant(){return $this->financialAccountant;}
	public function getPayrollAccountant(){return $this->payrollAccountant;}
	public function getAccountingSoftware(){return $this->accountingSoftware;}
	public function getProcessingType(){return $this->processingType;}
	public function getRoadTaxPayment(){return $this->roadTaxPayment;}
	public function getSalaryPayOffDay(){return $this->salaryPayOffDay;}
	public function getVatDepositPeriod(){return $this->vatDepositPeriod;}
	public function getBillingBy(){return $this->billingBy;}
	
	//#JG: Ticket #7 end

    //#JG: Ticket #107 start
    public function getContract(){return $this->contract;}
    public function getNote(){return $this->note;}
    public function getRoadTaxDeclarationDeliveryDate(){return $this->roadTaxDeclarationDeliveryDate;}
    public function getRoadTaxNumberOfCars(){return $this->roadTaxNumberOfCars;}
    public function getTaxesDeclarationDeliveryDate(){return $this->taxesDeclarationDeliveryDate;}
    public function getTaxesDocumentsDeliveryDate(){return $this->taxesDocumentsDeliveryDate;}
    //#JG: Ticket #107 end

    /**
     * @param \App\ClientModule\Model\type $externalDiskPath
     */
    public function setExternalDiskPath($externalDiskPath)
    {
        $this->externalDiskPath = $externalDiskPath;
    }

    /**
     * @return \App\ClientModule\Model\type
     */
    public function getExternalDiskPath()
    {
        return $this->externalDiskPath;
    }

    /**
     * @param int $pohodaID
     */
    public function setPohodaID($pohodaID)
    {
        $this->pohodaID = $pohodaID;
    }

    /**
     * @return int
     */
    public function getPohodaID()
    {
        return $this->pohodaID;
    }

    /**
     * @param int $dataAccessFee
     */
    public function setDataAccessFee($dataAccessFee)
    {
        $this->dataAccessFee = $dataAccessFee;
    }

    /**
     * @return int
     */
    public function getDataAccessFee()
    {
        return $this->dataAccessFee;
    }

    /**
     * @param int $dataAccessFeePaid
     */
    public function setDataAccessFeePaid($dataAccessFeePaid)
    {
        $this->dataAccessFeePaid = $dataAccessFeePaid;
    }

    /**
     * @return int
     */
    public function getDataAccessFeePaid()
    {
        return $this->dataAccessFeePaid;
    }


    /**
     * Translation function for billing type
     */
    public function getTypeOfBillingTranslation() {
        $type = '';
        switch($this->getTypeOfBilling()) {
            case Client::BILLING_SINGLETIME:
                $type = 'jednorázově';
                break;
            case Client::BILLING_FEE:
                $type = 'paušál';
                break;
            case Client::BILLING_HOURS:
                $type = 'hodinově';
                break;
            case Client::BILLING_ITEMS:
                $type = 'položkově';
                break;
            case Client::BILLING_GRATIS:
                $type = 'gratis';
                break;
        }

        return $type;
    }

	public function getTypeTranslation() {
		$type = '';
		switch($this->getTypeName()) {
			case Client::COMPANY:
				$type = 'Právnická osoba';
				break;
			case Client::PERSON:
				$type = 'Fyzická osoba (bez IČ)';
				break;
			case Client::TRADESMAN:
				$type = 'Fyzická osoba (OSVČ)';
				break;
		}

		return $type;
	}

	public function getDphPeriodicityTranslation() {
		$type = '';
		switch($this->getDphPeriodicity()) {
			case Client::FREQUENCY_NONE:
				$type = 'Není plátcem';
				break;
			case Client::FREQUENCY_MONTH:
				$type = 'Měsíční';
				break;
			case Client::FREQUENCY_QUARTER:
				$type = 'Čtvrtletní';
				break;
			case Client::FREQUENCY_YEAR:
				$type = 'Roční';
				break;
		}

		return $type;
	}

	public function getFrequencyOfBillingTranslation() {
		$type = '';
		switch($this->getFrequencyOfBilling()) {
			case Client::FREQUENCY_GRATIS:
				$type = 'Gratis';
				break;
			case Client::FREQUENCY_SINGLETIME:
				$type = 'Jednorázově';
				break;
			case Client::FREQUENCY_MONTH:
				$type = 'Měsíční';
				break;
			case Client::FREQUENCY_QUARTER:
				$type = 'Čtvrtletní';
				break;
			case Client::FREQUENCY_YEAR:
				$type = 'Roční';
				break;
		}

		return $type;
	}
	
	public function getAccountingServicesTranslation() {
		$type = '';
		switch($this->getAccountingServices()) {
			case Client::BILLING:
				$type = 'Účtování';
				break;
			case Client::CONTROLS:
				$type = 'Kontroly';
				break;
			case Client::BILLING_CONTROLS:
				$type = 'Účtování/kontroly';
				break;
			case Client::SINGLE_TIME:
				$type = 'Jednorázovky';
				break;
			case Client::BILLING_OTHER:
				$type = 'Ostatní';
				break;
			case Client::HOUR_RATE:
				$type = 'Hodinová sazba';
				break;
		}
		return $type;
	}
	public function getStatusClientTranslation() {
		$type = '';
		switch($this->getStatus()) {
			case Client::STATUS_ACTIVE:
				$type = 'Aktivní';
				break;
			case Client::STATUS_OLD:
				$type = 'Neaktivní';
				break;
		}
		return $type;
	}

	public function getClientContractTranslation() {
		$type = '';
		switch($this->getContract()) {
			case Client::YES:
				$type = 'Ano';
				break;
			case Client::NO:
				$type = 'Ne';
				break;
			case Client::READY_TO_SIGN:
				$type = 'K podpisu';
				break;
		}
		return $type;
	}

	public function getClientProcessingTypeTranslation() {
		$type = '';
		switch($this->getProcessingType()) {
			case Client::SINGLETIME:
				$type = 'Jednorázově';
				break;
			case Client::ACCOUNTING:
				$type = 'Účetnictví';
				break;
			case Client::ACCOUNTING_NONPROFITS:
				$type = 'Účetnictví neziskovky';
				break;
			case Client::TAX_REGISTER:
				$type = 'Daňová evidence';
				break;
			case Client::NONE:
				$type = 'Nic';
				break;
			case Client::OTHER:
				$type = 'Jiné';
				break;
		}
		return $type;
	}

	public function getWorkPlaceTranslation() {
		$type = '';
		switch($this->getWorkPlace()) {
			case 'undefined':
				$type = 'Neurčeno';
				break;
			case '':
				$type = '';
				break;
		}
		return $type;
	}
}

