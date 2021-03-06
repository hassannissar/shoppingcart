<?php namespace Syscover\ShoppingCart;

use Illuminate\Contracts\Support\Arrayable;

class Item implements Arrayable
{
    /**
     * The rowID of the cart item.
     *
     * @var string
     */
    public $rowId;

    /**
     * The ID of the cart item.
     *
     * @var int|string
     */
    public $id;

    /**
     * The name of the cart item.
     *
     * @var string
     */
    public $name;

    /**
     * The price without TAX of the cart item.
     *
     * @var float
     */
    public $price;

    /**
     * set if product is transportable.
     *
     * @var boolean
     */
    public $transportable;

    /**
     * Weight or unit to calculate ahippiing amount.
     *
     * @var int|float
     */
    public $weight;

    /**
     * The tax rules for the cart item.
     *
     * @var \Syscover\ShoppingCart\CartItemTaxRules;
     */
    public $taxRules;

    /**
     * The options for this cart item.
     *
     * @var array
     */
    public $options;

    /**
     * The quantity for this cart item.
     *
     * @var int|float
     */
    private $quantity;

    /**
     * The quantity for this cart item.
     *
     * @var int|float
     */
    public $subtotal;

    /**
     * The quantity for this cart item.
     *
     * @var int|float
     */
    public $taxAmount;

    /**
     * The quantity for this cart item.
     *
     * @var int|float
     */
    public $total;

    /**
     * Discount type of price rule
     *
     * @var int
     */
    public $discountType;

    /**
     * Discount percentage over subtotal from this item.
     *
     * @var int|float
     */
    private $discountSubtotalPercentage = 0;

    /**
     * Discount percentage over total from this item.
     *
     * @var int|float
     */
    private $discountTotalPercentage = 0;

    /**
     * The discount amount over subtotal from this item.
     *
     * @var int|float
     */
    private $discountSubtotalAmount = 0;

    /**
     * The discount amount over total from this item.
     *
     * @var int|float
     */
    private $discountTotalAmount = 0;

    /**
     * Discount amount if discount type is DISCOUNT_SUBTOTAL_FIXED_AMOUNT
     *
     * @var float
     */
    public $discountFixed = 0;


    /**
     * CartItem constructor.
     *
     * @param int|string                            $id
     * @param string                                $name
     * @param float                                 $quantity
     * @param float                                 $price
     * @param boolean                               $transportable
     * @param float                                 $weight
     * @param array|\Syscover\ShoppingCart\TaxRule  $taxRule
     * @param array                                 $options
     */
    public function __construct($id, $name, $quantity, $price, $weight = 1.000, $transportable = true, $taxRule = [], array $options = [])
    {
        if(empty($id))
            throw new \InvalidArgumentException('Please supply a valid identifier.');

        if(empty($name))
            throw new \InvalidArgumentException('Please supply a valid name.');

        if(strlen($price) < 0 || ! is_numeric($price))
            throw new \InvalidArgumentException('Please supply a valid price.');

        if(! is_bool($transportable))
            throw new \InvalidArgumentException('Please supply a valid transportable.');

        if(strlen($weight) < 0 || ! is_numeric($weight))
            throw new \InvalidArgumentException('Please supply a valid weight.');

        $this->id               = $id;
        $this->name             = $name;
        $this->price            = floatval($price);
        $this->transportable    = $transportable;
        $this->weight           = floatval($weight);
        $this->options          = new CartItemOptions($options);
        $this->taxRules         = new CartItemTaxRules();
        $this->rowId            = $this->generateRowId($id, $options);

        // add tax rule to taxRules property
        $this->addTaxRule($taxRule);

        // When set quantity, calculate all amounts, for this reason this is last function
        // called in constructor
        $this->setQuantity($quantity);
    }

    /**
     * magic method to make accessing the total, tax and subtotal properties
     *
     * @param   string      $attribute
     * @return  float|null
     */
    public function __get($attribute)
    {
        if($attribute === 'discountSubtotalPercentage')
        {
            return $this->discountSubtotalPercentage;
        }

        if($attribute === 'discountTotalPercentage')
        {
            return $this->discountTotalPercentage;
        }

        if($attribute === 'discountAmount')
        {
            return $this->discountSubtotalAmount + $this->discountTotalAmount;
        }
        return null;
    }

    /**
     * Returns the formatted unit price.
     *
     * @param   int       $decimals
     * @param   string    $decimalPoint
     * @param   string    $thousandSeperator
     * @return  string
     */
    public function getPrice($decimals = 2, $decimalPoint = ',', $thousandSeperator = '.')
    {
        return number_format($this->price, $decimals, $decimalPoint, $thousandSeperator);
    }

    /**
     * Returns array with all tax rates apply over item formated
     *
     * @param   int       $decimals
     * @param   string    $decimalPoint
     * @param   string    $thousandSeperator
     * @return  array
     */
    public function getTaxRates($decimals = 0, $decimalPoint = ',', $thousandSeperator = '.')
    {
        return array_map(function($taxRate) use ($decimals, $decimalPoint, $thousandSeperator) {
            return number_format($taxRate, $decimals, $decimalPoint, $thousandSeperator);
        },  $this->taxRules->pluck('taxRate')->toArray());
    }

    /**
     * Returns the formatted subtotal.
     * Subtotal is price for whole CartItem without TAX
     *
     * @param   int     $decimals
     * @param   string  $decimalPoint
     * @param   string  $thousandSeperator
     * @return  string
     */
    public function getSubtotal($decimals = 2, $decimalPoint = ',', $thousandSeperator = '.')
    {
        return number_format($this->subtotal, $decimals, $decimalPoint, $thousandSeperator);
    }

    /**
     * Returns the formatted tax amount.
     *
     * @param   int       $decimals
     * @param   string    $decimalPoint
     * @param   string    $thousandSeperator
     * @return  string
     */
    public function getTaxAmount($decimals = 2, $decimalPoint = ',', $thousandSeperator = '.')
    {
        return number_format($this->taxAmount, $decimals, $decimalPoint, $thousandSeperator);
    }

    /**
     * Returns the formatted subtotal.
     * Subtotal is price for whole CartItem without TAX
     *
     * @param   int     $decimals
     * @param   string  $decimalPoint
     * @param   string  $thousandSeperator
     * @return  string
     */
    public function getDiscountAmount($decimals = 2, $decimalPoint = ',', $thousandSeperator = '.')
    {
        return number_format($this->discountAmount, $decimals, $decimalPoint, $thousandSeperator);
    }

    /**
     * Returns the formatted total.
     * Total is price for whole CartItem with TAX
     *
     * @param   int     $decimals
     * @param   string  $decimalPoint
     * @param   string  $thousandSeperator
     * @return  string
     */
    public function getTotal($decimals = 2, $decimalPoint = ',', $thousandSeperator = '.')
    {
        return number_format($this->total, $decimals, $decimalPoint, $thousandSeperator);
    }

    /**
     * Generate a unique id for the cart item.
     *
     * @param   string  $id
     * @param   array   $options
     * @return  string
     */
    protected function generateRowId($id, array $options)
    {
        ksort($options);
        return md5($id . serialize($options));
    }

    /**
     * Get the quantity for this cart item.
     *
     * @return float
     */
    public function getQuantity()
    {
        return $this->quantity;
    }

    /**
     * Set the quantity for this cart item.
     *
     * @param   int|float $quantity
     * @return  \Syscover\ShoppingCart\Item
     */
    public function setQuantity($quantity)
    {
        if($quantity !== 0 && (empty($quantity) || ! is_numeric($quantity)))
            throw new \InvalidArgumentException('Please supply a valid quantity.');

        $this->quantity = $quantity;
        $this->calculateAmounts();

        return $this;
    }


    /**
     * Get format discountSubtotalPercentage over this cart item.
     *
     * @param   int     $decimals
     * @param   string  $decimalPoint
     * @param   string  $thousandSeperator
     * @return  string
     */
    public function getDiscountSubtotalPercentage($decimals = 0, $decimalPoint = ',', $thousandSeperator = '.')
    {
        return number_format($this->discountSubtotalPercentage, $decimals, $decimalPoint, $thousandSeperator);
    }

    /**
     * Set subtotal discount percentage over this cart item.
     *
     * @param   int|float   $discountSubtotalPercentage
     * @return  \Syscover\ShoppingCart\Item
     */
    public function setDiscountSubtotalPercentage($discountSubtotalPercentage)
    {
        if($discountSubtotalPercentage !== 0 && (empty($discountSubtotalPercentage) || ! is_numeric($discountSubtotalPercentage)))
            throw new \InvalidArgumentException('Please supply a valid discount percentage.');

        // set discount subtotal percentage
        $this->discountSubtotalPercentage = (float) $discountSubtotalPercentage;

        $this->calculateAmounts();

        return $this;
    }

    /**
     * Set total discount percentage over this cart item.
     *
     * @param   int|float   $discountTotalPercentage
     * @return  \Syscover\ShoppingCart\Item
     */
    public function setDiscountTotalPercentage($discountTotalPercentage)
    {
        if($discountTotalPercentage !== 0 && (empty($discountTotalPercentage) || ! is_numeric($discountTotalPercentage)))
            throw new \InvalidArgumentException('Please supply a valid discount percentage.');

        // set discount total percentage
        $this->discountTotalPercentage = (float) $discountTotalPercentage;

        $this->calculateAmounts();

        return $this;
    }

    /**
     * Calculate all amounts, this function is called, when change any property from cartItem
     *
     * @return void
     */
    private function calculateAmounts()
    {
        // subtotal calculate
        if(config('shoppingcart.taxProductPrices') == Cart::PRICE_WITHOUT_TAX || $this->taxRules === null || $this->taxRules->count() == 0)
        {
            // calculate subtotal
            $this->subtotal = $this->quantity * $this->price;

            // calculate discount amount if has discount subtotal percentage
            if($this->discountSubtotalPercentage > 0)
                $this->discountSubtotalAmount = ($this->subtotal * $this->discountSubtotalPercentage) / 100;

            $this->calculateAmountsOverPriceWithoutTax();

            // calculate discount and tax over total amount, if has discount percentage
            if($this->discountTotalPercentage > 0)
            {
                $this->discountTotalAmount = ($this->total * $this->discountTotalPercentage) / 100;
            }
        }
        elseif(config('shoppingcart.taxProductPrices') == Cart::PRICE_WITH_TAX)
        {
            //calculate total
            $this->total = $this->quantity * $this->price;

            // calculate discount and tax over total amount, if has discount percentage
            if($this->discountTotalPercentage > 0)
            {
                $this->discountTotalAmount  = ($this->total * $this->discountTotalPercentage) / 100;
                $this->total                -= $this->discountTotalAmount;
            }

            $this->calculateAmountsOverPriceWitTax();

            // calculate discount and tax over subtotal amount, if has discount percentage
            if($this->discountSubtotalPercentage > 0)
            {
                $this->discountSubtotalAmount = ($this->subtotal * $this->discountSubtotalPercentage) / 100;
                $this->calculateAmountsOverPriceWithoutTax();
            }


        }
    }

    /**
     *
     */
    private function resetTaxAmounts()
    {
        foreach ($this->taxRules as &$taxRule)
        {
            $taxRule->taxAmount = 0;
        }
    }

    /**
     * Calculate discount and tax over subtotal amount
     *
     * @return void
     */
    private function calculateAmountsOverPriceWithoutTax()
    {
        // reset tax amounts to calculate amounts again
        $this->resetTaxAmounts();

        if($this->taxRules === null || $this->taxRules->count() == 0)
        {
            $this->taxAmount = 0;
        }
        else
        {
            // calculate amounts of each taxRule
            $taxRules           = $this->taxRules->sortBy('priority');
            $lastPriority       = 0;
            $baseToCalculate    = $this->subtotal - $this->discountSubtotalAmount;
            foreach ($taxRules as &$taxRule)
            {
                if($lastPriority == $taxRule->priority)
                {
                    $taxRule->taxAmount = $baseToCalculate * ($taxRule->taxRate / 100);
                }
                else
                {
                    $lastPriority = $taxRule->priority;

                    // if is a different priority, calculate tax over subtotal plus previous tax amounts
                    $baseToCalculate    += $taxRules->sum('taxAmount');
                    $taxRule->taxAmount = $baseToCalculate * ($taxRule->taxRate / 100);
                }
            }

            // set total tax from this car item
            $this->taxAmount = $this->taxRules->sum('taxAmount');
        }

        // first calculate total with discountSubtotal
        $this->total = ($this->subtotal - $this->discountSubtotalAmount) + $this->taxAmount;

        // last subtracting discountTotal from total
        $this->total -= $this->discountTotalAmount;
    }

    /**
     * Calculate discount and tax over subtotal amount
     *
     * @return void
     */
    private function calculateAmountsOverPriceWitTax()
    {
        // reset tax amounts to calculate amounts again
        $this->resetTaxAmounts();

        // calculate tax
        $totalAux       = $this->total;
        $taxRules       = $this->taxRules->sortByDesc('priority');  // sort taxRules desc direction to get subtotal
        $lastPriority   = null;
        $taxAmountAux   = 0;
        foreach ($taxRules as &$taxRule)
        {
            if($lastPriority === null || $lastPriority != $taxRule->priority)
            {
                $lastPriority = $taxRule->priority;
                $totalAux     -= $taxAmountAux;
            }

            // get total taxRate from taxRules with the same priority
            $taxRateAux         = $taxRules->where('priority', $taxRule->priority)->sum('taxRate');

            $taxAmountAux       = ($taxRateAux * $totalAux) / ($taxRateAux + 100);
            $taxRule->taxAmount = ($taxAmountAux * $taxRule->taxRate) / $taxRateAux;
        }

        $this->taxAmount    = $taxRules->sum('taxAmount');
        $this->subtotal     = $this->total - $this->taxAmount;
    }

    /**
     * Add TaxRule to cartItemTaxRules object
     *
     * @param   array|\Syscover\ShoppingCart\TaxRule        $taxRule
     * @return  \Syscover\ShoppingCart\CartItemTaxRules
     */
    private function addTaxRule($taxRule)
    {
        if(is_array($taxRule))
        {
            return array_map(function ($item) {
                return $this->addTaxRule($item);
            }, $taxRule);
        }

        // sum ratesy if exist a tax rule with de same id
        if($this->taxRules->has($taxRule->id))
            $taxRule->taxRate = $taxRule->taxRate + $this->taxRules->get($taxRule->id)->taxRate;

        $this->taxRules->put($taxRule->id, $taxRule);
    }















    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'rowId'         => $this->rowId,
            'id'            => $this->id,
            'name'          => $this->name,
            'quantity'      => $this->quantity,
            'price'         => $this->price,
            'transportable' => $this->transportable,
            'weight'        => $this->weight,
            'options'       => $this->options,
        ];
    }
}