<?php

namespace App\View\Components\Page;

use Illuminate\View\Component;

class Actions extends Component
{
    public $primary;

    public $secondary;

    public $primaryLink;

    public $secondaryLink;

    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct($primary = 'none', $secondary = 'none', $primaryLink = '', $secondaryLink = '')
    {
        $this->primary = $primary;
        $this->secondary = $secondary;
        $this->primaryLink = $primaryLink;
        $this->secondaryLink = $secondaryLink;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.page.actions');
    }
}
