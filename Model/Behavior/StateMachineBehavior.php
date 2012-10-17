<?php
/**
 * State Machine Behavior
 *
 * A basic finite state machine behavior for CakePHP 2.x.
 *
 * Describe a model's states and the events that cause transitions between
 * them, then trigger these events to change your model to a new state.
 *
 * @copyright     Graeme Tait @burriko
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 *
 **/

class StateMachineBehavior extends ModelBehavior
{
	/**
	 * Initialise behavior by reading settings and creating model relationship
	 * @return void
	 */
	public function setup(Model $model, $config = array())
	{
		// create hasMany relationship between model and it's state model
		$state_model_alias = $model->alias . 'State';
		$model->bindModel(array('hasMany' => array($state_model_alias)), false);

		// save some useful information in settings
		$foreign_key = $model->hasMany[$state_model_alias]['foreignKey'];
		$this->settings[$model->alias]['foreign_key'] = $foreign_key;
		$this->settings[$model->alias]['state_model'] = $model->{$state_model_alias};
	}

	/**
	 * If a new record has been created then set it's initial state
	 * @param  bool $created Whether the record is new
	 * @return void
	 */
	public function afterSave(Model $model, $created)
	{
		if ($created) {
			$this->initialiseState($model);
		}
	}

	/**
	 * Retrieve the current state of the model
	 * @return string The current state
	 */
	public function getCurrentState(Model $model)
	{
		return $model->field('state');
	}

	/**
	 * Query if the specified state is the current state
	 * @param  string $state A state
	 * @return bool
	 */
	public function stateIs(Model $model, $state)
	{
		return $this->getCurrentState($model) === $state;
	}

	/**
	 * Transition from the current state to a new state based on the event
	 * @param  string $event The transitioning event
	 * @return bool          Whether the state change was successful
	 */
	public function transition(Model $model, $event)
	{
		$current_state = $this->getCurrentState($model);
		if (isset($model->states[$current_state][$event])) {
			$next_state = $model->states[$current_state][$event];
			return $this->changeState($model, $next_state);
		}
		return false;
	}

	/**
	 * Look up what the initial state should be
	 * @return string The initial state
	 */
	public function getInitialState(Model $model)
	{
		$states = $this->listStates($model);
		return $states[0];
	}

	/**
	 * Fetch states as listed in model
	 * @return array List of states
	 */
	public function listStates(Model $model)
	{
		$states = array();
		foreach ($model->states as $key => $value) {
			$states[] = is_array($value) ? $key : $value;
		}
		return $states;
	}

	/**
	 * Prepare formatted list of states for select input
	 * @return array Formatted list of states
	 */
	public function listStatesForSelect(Model $model)
	{
		$select_states['all'] = 'All';
		$states = $model->listStates();
		foreach ($states as $state) {
			$select_states[$state] = Inflector::humanize($state);
		}
		return $select_states;
	}

	/**
	 * Set state to first in the array
	 * @return void
	 */
	protected function initialiseState(Model $model)
	{
		if (count($model->states) > 0) {
			$this->changeState($model, $this->getInitialState($model));
		}
	}

	/**
	 * Change state by creating new state record
	 * @param  string $state The new state
	 * @return bool          Whether successful
	 */
	protected function changeState(Model $model, $state)
	{
		if ($this->setModelState($model, $state) and
			 $this->createStateRecord($model, $state)) {
			$this->callStateMethod($model, $state);
			return true;
		}
		return false;
	}

	/**
	 * Save current state in model
	 * @param string $state The state
	 * @return bool          Whether successful
	 */
	protected function setModelState(Model $model, $state)
	{
		$model->read();
		$model->set('state', $state);
		return $model->save();
	}

	/**
	 * Create new record in state model
	 * @param  string $state The state
	 * @return bool          Whether successful
	 */
	protected function createStateRecord(Model $model, $state)
	{
		$state_model = $this->settings[$model->alias]['state_model'];
		// make sure we're creating a new record
		$state_model->create();

		$state_data = array(
			$state_model->alias => array(
				'state' => $state,
				$this->settings[$model->alias]['foreign_key'] => $model->id
			)
		);
		return (bool) $state_model->save($state_data);
	}

	/**
	 * Execute callback method
	 * @param string $state The state
	 */
	public function callStateMethod(Model $model, $state)
	{
		$method = '_onState' . Inflector::camelize($state);
		if (method_exists($model, $method)) {
			$model->$method();
		}
	}
}