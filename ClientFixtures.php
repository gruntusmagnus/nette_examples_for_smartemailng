<?php

namespace App\ClientModule\Model;


use Orakulum\Model\AbstractFixture;
use Doctrine\Common\Persistence\ObjectManager;

use Doctrine\Common\DataFixtures\DependentFixtureInterface;

/**
 * Description of AddressFixtures
 *
 * @author JG
 */
class ClientFixtures extends AbstractFixture implements DependentFixtureInterface{
	
	
	public function load(ObjectManager $em){
		$this->setEntityManager($em);
		$repo = $em->getRepository('App\ClientModule\Model\Client');
		/*
		$corona = $repo->createNew(Client::COMPANY, '12345678', 'Corona a.s', $this->getReference('husitska-address'));
		$corona->addMaintainer($this->getReference('hanka-employee'));
		$repo->save($corona);
		
		$sasa = $repo->createNew(Client::TRADESMAN, '14725836', 'Novotný Alexej', $this->getReference('ostredecka-address'));
		$sasa->addMaintainer($this->getReference('hanka-employee'));
		$repo->save($sasa);
		*/
	}
	
	
	
	public function getDependencies(){
		return array(
			'App\CommonModule\Model\ContactFixtures',
			'App\CommonModule\Model\AddressFixtures',
			'App\EmployeeModule\Model\EmployeeFixtures',
		);
	}
	
	
}
