<?php
/**
 * MyThings System Plugin
 *
 * @author   WebMechanic.biz
 * @version  0.1.0
 * @license  CC-BY-NC 3.0/de
 */

class plgSystemMythings extends JPlugin
{
	/**
	 * Standard Konstruktor, läd die Sprachdateien.
	 *
	 * @param object $subject Dispatcher
	 * @param array  $config  Plugin config
	 */
	public function __construct(&$subject, $config = array())
	{
		parent::__construct($subject, $config);
		$this->loadLanguage();
	}

	/**
	 * Ausgelöst nachdem der User authentifiziert wurde und dabei ist sind anzumelden.
	 *
	 * - $options[clientid] client-id über den die Anmeldung erfolgt
	 *
	 * @param	array	$user		Hält Nutzerdaten 'username', 'id'
	 * @param	array	$options	Loginoptionen (remember, autoregister, group)
	 * @return	boolean true  Um mit dem Loginprozess fortzufahren.
	 */
	public function onUserLogin($userdata, $options = array())
	{
		if (self::inAdmin()) {
			return true;
		}

		// brilliant: neue Suche nach Name nötig, da uns die ID hier fehlt
		$user = JUser::getInstance($userdata['username']);

		// User gefunden: Ausleihe testen
		if ($user->id) {
			$this->findUserThings($user);
		}

		return true;
	}

	/**
	 * Form Model events. Prepare data, fired on data preparation.
	 *
	 * com_contact context: 'com_users.profile'
	 * com_user    context: 'com_users.profile' (formerly onPrepareUserProfileData),
	 * 						'com_users.registration'
	 *
	 * 'com_users.profile' $data == JUser instance
	 *
	 * @param string $context
	 * @param object $data
	 */
	public function onContentPrepareData($context, $data)
	{
		if (self::inAdmin()) {
			return true;
		}

		// User Profil im Frontend?
		if ($context == 'com_users.profile')
		{
			// aus der Session holen: hier ein stdClass!!
			$things = JFactory::getApplication()->getUserState('com_mythings.things');
			if ($things) {
				$this->notifyUserAboutThings($things->lent, $things->overdue);
			}
		}

		return true;
	}

	/**
	 * Form Model events. Fire on form preparation (before content plugins)
	 *
	 * $data feat. a record from the __extensions table.
	 * - xml: SimpleXMLElement of the manifest
	 * - params: array of current params
	 *
	 * 'com_users.profile' $data == JUser instance
	 *
	 * @param JForm  $form
	 * @param JObject|array $data array "after save", JObject "on read" %-/
	 */
	public function x_onContentPrepareForm(JForm $form, $data)
	{
		if (self::inAdmin()) {
			return true;
		}

		if ($form->getName() == 'com_users.profile')
		{
			// hier könnte man am Formular rumspielen
		}

		return true;
	}

	/**
	 * Performs an elaborate test to find out whether the code runs in the
	 * context of the so-called Joomla Administrator Client, aka Back-end.
	 *
	 * @since  1.0.0
	 * @return boolean
	 */
	static public function inAdmin()
	{
		return (JFactory::getApplication() instanceof JAdministrator);
	}

	/**
	 * Findet die Anzahl der ausgeliehenden und überfälligen Dinge des $user.
	 * Speichert die Werte in der Session und zeigt App-Messages an, um den
	 * User daran zu erinnern.
	 *
	 * @param JUser $user
	 * @return plgSystemMythings
	 */
	protected function findUserThings(JUser $user)
	{
		if ($user->id == 0)
		{
			return $this;
		}

		// Datenbank und frisches Abfrageobjekt
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);

		// Anzahl Dinge die User ausgeliehen hat
		$query->select('COUNT(id) AS n')
			->from('#__mythings')
			->where('lent_by_id = '. (int) $user->id);

		$db->setQuery($query);
		$lent = (int) $db->loadResult();

		// in der Session festhalten
		JFactory::getApplication()->setUserState('com_mythings.things.lent', $lent);

		// nichts geliehen? raus.
		if ($lent == 0) {
			return $this;
		}

		// Auswahl verfeinern nach Datum
		$query->where('lent_to >= CURDATE()');
		$overdue = (int) $db->loadResult();

		// in der Session festhalten
		JFactory::getApplication()->setUserState('com_mythings.things.overdue', $overdue);

		$this->notifyUserAboutThings($lent, $overdue);
	}

	protected function notifyUserAboutThings($lent, $overdue)
	{
		$msg = JText::plural('MYTHINGS_LENT_TOTAL', $lent);
		JFactory::getApplication()->enqueueMessage($msg, 'message');

		$msg = JText::plural('MYTHINGS_LENT_OVERDUE', $overdue);
		JFactory::getApplication()->enqueueMessage($msg, 'warning');
	}

}
