<?php

	namespace AceIDE\Editor\Modules;

	use AceIDE\Editor\IDE;

	interface Module {
		public function setup_hooks(IDE $ide);
	}
