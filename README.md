# CakePHP Finite State Machine Behavior

A basic finite state machine behavior for CakePHP 2.x. A version for CakePHP 1 can be found in the branch cakephp1.

Describe a model's states and the events that cause transitions between them, then trigger these events to change your model to a new state.

## Installation

1. Copy the behavior to models/behaviors in your app.
2. In your model add:

		public $actsAs = array('StateMachine');

3. Create a new database table to store the states using the following schema, adjusting the table name and foreign key to match your model. For example, if you are adding states to a model name Placement you would use the following.

		CREATE TABLE `placement_states` (
		  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
		  `placement_id` int(11) unsigned NOT NULL,
		  `state` varchar(50) NOT NULL DEFAULT '',
		  `created` datetime NOT NULL,
		  PRIMARY KEY (`id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8;

4. Add a new varchar field to your model's table called 'state'.

		ALTER TABLE `placements` ADD `state` VARCHAR(50);

## Usage

1. In your model describe the states and transitions you need like so.

		public $states = array('state' => array('event' => 'new_state'));

	Here's an example.

		public $states = array(
		  'advertised' => array(
		    'select_appropriate_applicants' => 'shortlisted',
		    'no_appropriate_applicants' => 'unplaced'
		  ),
		  'shortlisted' => array(
		    'select_for_interview' => 'interviews',
		    'no_appropriate_applicants' => 'unplaced'
		  ),
		  'interviews' => array(
		    'select_successful_candidate' => 'placed',
		    'no_appropriate_applicants' => 'unplaced'
		  ),
		  'placed' => array(
		    'placement_complete' => 'complete',
		    'problem_with_placement' => 'unplaced'
		  ),
		  'unplaced' => array(
		    'readvertise' => 'advertised'
		  ),
		  'complete'
		);

2. When a new record is created by that model it will be assigned the first state in the list as it's initial state.

3. To transition to a new state call pass the name of an event to the transition() method.

		$this->Model->transition('event');

	In this example if the Placement model was in the 'interviews' state it would transition to the 'placed' state.

		$this->Placement->transition('select_successful_candidate');

4. Every state change is recorded in the database. Every model that uses the StateMachine behavior will have an appropriate state model named ModelnameState. The model is related to this with a hasMany relationship.  You can therefore use this model to look up state changes however you want.

		$this->Placement->PlacementState->findByPlacementId($placement_id);

	You can also find the current state of a model from the getCurrentState() method.

		// set model id if not already set
		$this->Placement->id = $placement_id;
		$this->Placement->getCurrentState(); // returns 'placed'

## Notes

- The current state of a record is also stored in the 'state' column of the model. This is to make it easier when querying the model by state.
- A callback method is supported for state changes. This should be titled _onState<name of state>(). For example, to run a method when the model changes to a state named 'placed' add the following method to your model.

		public function _onStatePlaced() {}
