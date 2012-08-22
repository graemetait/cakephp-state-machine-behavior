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
			$this->setInitialState($model);
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

	// set state to first in the array
	protected function setInitialState(Model $model)
	{
		if (count($model->states) > 0) {
			reset($model->states);
			$initial_state = key($model->states);
			$this->changeState($model, $initial_state);
		}
	}

	// change state by creating new state record
	protected function changeState(Model $model, $state)
	{
		$model->set('state', $state);
		$model->save();

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
}