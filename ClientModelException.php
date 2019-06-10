<?php

namespace App\ClientModule\Model;

use Orakulum\Model\ModelException;

/**
 * Description of UserException
 *
 * @author JG
 */
class ClientModelException extends ModelException{

	public static $messages = array(
		'can-not-delete-client' => array(
				'en' => "Can not delete client '%s'.",
				'cs' => "Nepodařilo se smazat data o klientovi s IČ '%s'."
		),
		'can-not-load-client-data-by-ico' => array(
			'en' => "Can not load client data by identification number '%s'.",
			'cs' => "Nepodařilo se načíst data o klientovi s IČ '%s'."
		),
		'can-not-update-client' => array(
				'en' => "Can not update client.",
				'cs' => "Nepodařilo se aktualizovat klienta.",
		),
		'can-not-create-clieant-because-client-alredy-exists' => array(
			'en' => "Can not create client with id '%s' because client alredy exists.",
			'cs' => "Klient s ič '%s' v systému již existuje. Nelze vytvořit duplicitní záznam."
		),
			
			
			
		//#PeSp: Ticket #29	
			'can-not-find-clients' => array( /* args($offset, $limit, $e) */
					'en' => "Can not find clients.",
					'cs' => "Nepodařilo se nalézt klienty.",
			),
			'can-not-count-clients' => array(
					'en' => "Can not count clients.",
					'cs' => "Nepodařilo se určit počet klientů.",
			),
			'can-not-find-allowed-clients' => array( /* args($limit, $offset, $sorting, $e) */
					'en' => "Can not find allowed clients.",
					'cs' => "Nepodařilo se najít aktivní (allowed) klienty.",
			),
			'can-not-create-client-because-seat-country-was-not-found' => array( /* args($seatData['country']['id']) */
					'en' => "Can not create client because seat country '%s' was not found.",
					'cs' => "Nepodařilo se vytvořit klienta, protože země '%s' adresy uvedeného sídla nebyla nalezena.",
			),
			'can-not-create-client-because-seat-is-not-valid-address' => array(
					'en' => "Can not create client because seat is not valid address.",
					'cs' => "Nepodařilo se vytvořit klienta, protože sídlo není platná adresa.",
			),
			'can-not-create-client-because-can-not-create-manager' => array(
					'en' => "Can not create client because can not create manager.",
					'cs' => "Nepodařilo se vytvořit klienta, protože se nenapodařilo vytvořit manažera.",
			),
			'can-not-create-client-because-maintainer-not-found' => array( /* args($id) */
					'en' => "Can not create client because maintainer (id:%s) not found.",
					'cs' => "Nepodařilo se vytvořit klienta, protože vybraný zodpovědný zaměstnanec (id:%s) nebyl nalezen.",
			),
			'can-not-create-client-because-main-accountant-not-found' => array( /* args($id) */
					'en' => "Can not create client because main accountant (emp-id:%s) not found.",
					'cs' => "Nepodařilo se vytvořit klienta, protože vybraný hlavní účetní (emp-id:%s) nebyl nalezen.",
			),
			'can-not-create-client-because-financial-accountant-not-found' => array( /* args($id) */
					'en' => "Can not create client because financial accountant (emp-id:%s) not found.",
					'cs' => "Nepodařilo se vytvořit klienta, protože vybraný finanční účetní (emp-id:%s) nebyl nalezen.",
			),
			'can-not-create-client-because-payroll-accountant-not-found' => array( /* args($id) */
					'en' => "Can not create client because payroll accountant (emp-id:%s) not found.",
					'cs' => "Nepodařilo se vytvořit klienta, protože vybraný mzdový účetní (emp-id:%s) nebyl nalezen.",
			),
			'can-not-update-client-because-employee-not-found' => array(
					'en' => "Can not update client because employee not found.",
					'cs' => "Nepodařilo se aktualizovat klienta, protože navázaný zaměstanec nebyl nalezen.",
			),
			'can-not-update-client-because-client-not-found' => array(
					'en' => "Can not update client because client\'s record was not found.",
					'cs' => "Nepodařilo se aktualizovat klienta, protože klientův databázový záznam nebyl nalezen.",
			),
			'can-not-create-client' => array( /* args($ico, $name) */
					'en' => "Can not create client (ico:%s, name:%s).",
					'cs' => "Nepodařilo se vytvořit klienta (ičo:%s, jméno:%s).",
			),
			'can-not-find-all-clients' => array(
					'en' => "Can not find all clients.",
					'cs' => "Nepodařilo se nalézt všechny klienty.",
			),
			'can-not-find-client' => array( /* args($id, $e) */
					'en' => "Can not find client (id:%s).",
					'cs' => "Nepodařilo se nalézt klienta (id:%s).",
			),
			'can-not-find-client-by-ico' => array( /* args($ico) */
					'en' => "Can not find client by ico (%s).",
					'cs' => "Nepodařilo se nalézt klienta podle IČO (%s)",
			),
			'can-not-update-client-because-someone-update-this-record' => array(
					'en' => "Can not update client because someone update this record.",
					'cs' => "Nepodařilo se aktualizovat klienta, protože někdo přávě daný záznam pozměnil.",
			),

			'pohoda-file-already-exists' => array(
				'en' => "Pohoda file already exists for this year.",
				'cs' => "Soubor pohody již pro tento rok existuje.",
			),
	);
	
}

