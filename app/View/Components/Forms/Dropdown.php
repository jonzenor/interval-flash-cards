<?php

namespace App\View\Components\Forms;

use Illuminate\View\Component;

class Dropdown extends Component
{
    public $name;

    public $label;

    public $values;

    public $selected;

    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct($name, $label, $values, $selected = '')
    {
        $this->name = $name;
        $this->label = $label;
        $this->values = $values;
        $this->selected = $selected;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.forms.dropdown');
    }
}
