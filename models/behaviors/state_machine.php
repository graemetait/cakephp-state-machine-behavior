<?php
/**
 * State Machine Behavior
 *
 * A basic finite state machine behavior for CakePHP 1.x.
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
	public function setup(Model $model)
	{
		// create hasMany relationship between model and it's state model
		$state_model_alias = $model->alias . 'State';
		$model->bindModel(array('hasMany' => array($state_model_alias)), false);

		// save some useful information in settings
		$foreign_key = $model->hasMany[$state_model_alias]['foreignKey'];
		$this->settings[$model->alias]['foreign_key'] = $foreign_key;
		$this->settings[$model->alias]['state_model'] = $model->{$state_model_alias};
	}

	// if new record then set initital state
	public function afterSave(Model $model, $created)
	{
		if ($created) {
			$this->initialiseState($model);
		}
	}

	// return current event as string
	public function getCurrentState(Model $model)
	{
		return $model->field('state');
	}

	// transition to a new state based on current state and event
	public function transition(Model $model, $event)
	{
		$current_state = $this->getCurrentState($model);
		if (isset($model->states[$current_state][$event])) {
			$next_state = $model->states[$current_state][$event];
			return $this->changeState($model, $next_state);
		}
		return false;
	}

	public function getInitialState(Model $model)
	{
		$states = $this->listStates($model);
		return $states[0];
	}

	public function listStates(Model $model)
	{
		$states = array();
		foreach ($model->states as $key => $value) {
			$states[] = is_array($value) ? $key : $value;
		}
		return $states;
	}

	// prepare formatted list of states for select input
	public function listStatesForSelect(Model $model)
	{
		$select_states['all'] = 'All';
		$states = $model->listStates();
		foreach ($states as $state) {
			$select_states[$state] = Inflector::humanize($state);
		}
		return $select_states;
	}

	// set state to first in the array
	protected function initialiseState(Model $model)
	{
		if (count($model->states) > 0) {
			$this->changeState($model, $this->getInitialState($model));
		}
	}

	// change state by creating new state record
	protected function changeState(Model $model, $state)
	{
		if ($this->setModelState($model, $state) and
			 $this->createStateRecord($model, $state)) {
			$this->callStateMethod($model, $state);
			return true;
		}
		return false;
	}

	// save current state in model
	protected function setModelState(Model $model, $state)
	{
		$model->read();
		$model->set('state', $state);
		return $model->save();
	}

	// create new record in state model
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

	public function callStateMethod(Model $model, $state)
	{
		$method = '_onState' . Inflector::humanize($state);
		if (method_exists($model, $method)) {
			$model->$method();
		}
	}
}