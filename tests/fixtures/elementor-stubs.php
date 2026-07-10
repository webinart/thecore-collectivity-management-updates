<?php

namespace Elementor {
	class Widget_Base {
		public function __construct( $data = array(), $args = null ) {}
	}

	class Widgets_Manager {
		public $widgets = array();

		public function register( $widget ) {
			$this->widgets[] = $widget;
		}
	}
}

namespace Elementor\Modules\AtomicWidgets\Elements\Base {
	class Atomic_Widget_Base {
		public function __construct( $data = array(), $args = null ) {}
	}
}
