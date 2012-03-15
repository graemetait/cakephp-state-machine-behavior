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
	protected $model, $state_model, $foreign_key;

	public function setup(Model $model)
	{
		// create hasMany relationship between model and it's state model
		$state_model_alias = $model->alias . 'State';
		$model->bindModel(array('hasMany' => array($state_model_alias)), false);

		// set local references to models
		$this->model = $model;
		$this->state_model = $this->model->{$state_model_alias};

		$this->foreign_key = Inflector::underscore($this->model->alias) . '_id';
	}

	// if new record then set initital state
	public function afterSave(Model $model, $created)
	{
		if ($created) {
			$this->setInitialState();
		}
	}

	// return current event as string
	public function getCurrentState(Model $model)
	{
		$state = $this->state_model->find('first', array(
			'conditions' => array($this->foreign_key => $this->model->id),
			'order' => array('created DESC'),
			'fields' => array('state')
		));
		return $state[$this->state_model->alias]['state'];
	}

	// transition to a new state based on current state and event
	public function transition(Model $model, $event)
	{
		$current_state = $this->getCurrentState();
		if (isset($model->states[$current_state][$event])) {
			$next_state = $model->states[$current_state][$event];
			return $this->changeState($next_state);
		}
		return false;
	}

	// set state to first in the array
	protected function setInitialState()
	{
		if (count($this->model->states) > 0) {
			reset($this->model->states);
			$initial_state = key($this->model->states);
			$this->changeState($initial_state);
		}
	}

	// change state by creating new state record
	protected function changeState($state)
	{
		// make sure we're creating a new record
		$this->state_model->create();

		$state_data = array(
			$this->state_model->alias => array(
				'state' => $state,
				$this->foreign_key => $this->model->id
			)
		);
		return (bool) $this->state_model->save($state_data);
	}
}