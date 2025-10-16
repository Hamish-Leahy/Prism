<?php

namespace Prism\Backend\Services;

use Prism\Backend\Models\ExpenseEstimate;

class ExpenseEstimationService
{
    private array $serviceProviders = [
        'airbnb' => 'AirbnbEstimationProvider',
        'hotel' => 'HotelEstimationProvider',
        'flight' => 'FlightEstimationProvider',
        'restaurant' => 'RestaurantEstimationProvider',
        'event' => 'EventEstimationProvider',
        'transport' => 'TransportEstimationProvider'
    ];

    public function estimateExpense(
        string $service,
        string $serviceName,
        array $parameters = []
    ): ExpenseEstimate {
        $provider = $this->getProvider($service);
        $estimate = $provider->estimate($serviceName, $parameters);
        
        return new ExpenseEstimate(
            uniqid(),
            $service,
            $serviceName,
            $estimate['cost'],
            $estimate['currency'] ?? 'USD',
            $parameters['location'] ?? null,
            $parameters['date'] ?? null,
            $parameters['duration'] ?? null,
            $parameters['participants'] ?? null,
            $estimate['metadata'] ?? []
        );
    }

    public function getServiceSuggestions(string $query): array
    {
        $suggestions = [];
        
        foreach ($this->serviceProviders as $service => $providerClass) {
            $provider = $this->getProvider($service);
            $serviceSuggestions = $provider->getSuggestions($query);
            $suggestions = array_merge($suggestions, $serviceSuggestions);
        }
        
        return array_slice($suggestions, 0, 10);
    }

    public function getPopularDestinations(string $service): array
    {
        $provider = $this->getProvider($service);
        return $provider->getPopularDestinations();
    }

    public function getPriceRanges(string $service, ?string $location = null): array
    {
        $provider = $this->getProvider($service);
        return $provider->getPriceRanges($location);
    }

    private function getProvider(string $service): EstimationProviderInterface
    {
        if (!isset($this->serviceProviders[$service])) {
            throw new \InvalidArgumentException("Unknown service: {$service}");
        }
        
        $providerClass = "Prism\\Backend\\Services\\EstimationProviders\\{$this->serviceProviders[$service]}";
        
        if (!class_exists($providerClass)) {
            throw new \RuntimeException("Provider class not found: {$providerClass}");
        }
        
        return new $providerClass();
    }
}

interface EstimationProviderInterface
{
    public function estimate(string $serviceName, array $parameters): array;
    public function getSuggestions(string $query): array;
    public function getPopularDestinations(): array;
    public function getPriceRanges(?string $location = null): array;
}

class AirbnbEstimationProvider implements EstimationProviderInterface
{
    public function estimate(string $serviceName, array $parameters): array
    {
        $baseCost = $this->getBaseCost($parameters['location'] ?? 'unknown');
        $duration = $parameters['duration'] ?? 1;
        $participants = $parameters['participants'] ?? 1;
        
        // Adjust for seasonality
        $seasonMultiplier = $this->getSeasonMultiplier($parameters['date'] ?? null);
        
        // Adjust for property type
        $propertyMultiplier = $this->getPropertyMultiplier($serviceName);
        
        $cost = $baseCost * $duration * $seasonMultiplier * $propertyMultiplier;
        
        // Adjust for number of participants (shared costs)
        if ($participants > 1) {
            $cost = $cost * (0.7 + (0.3 / $participants));
        }
        
        return [
            'cost' => round($cost, 2),
            'currency' => 'USD',
            'metadata' => [
                'baseCost' => $baseCost,
                'duration' => $duration,
                'participants' => $participants,
                'seasonMultiplier' => $seasonMultiplier,
                'propertyMultiplier' => $propertyMultiplier
            ]
        ];
    }

    public function getSuggestions(string $query): array
    {
        $suggestions = [
            'Cozy Studio in Downtown',
            'Modern Apartment with City View',
            'Charming House in Historic District',
            'Luxury Condo with Pool',
            'Budget-Friendly Shared Room'
        ];
        
        return array_filter($suggestions, function($suggestion) use ($query) {
            return stripos($suggestion, $query) !== false;
        });
    }

    public function getPopularDestinations(): array
    {
        return [
            'New York, NY' => ['baseCost' => 150, 'currency' => 'USD'],
            'San Francisco, CA' => ['baseCost' => 180, 'currency' => 'USD'],
            'Los Angeles, CA' => ['baseCost' => 120, 'currency' => 'USD'],
            'Paris, France' => ['baseCost' => 100, 'currency' => 'EUR'],
            'London, UK' => ['baseCost' => 130, 'currency' => 'GBP'],
            'Tokyo, Japan' => ['baseCost' => 80, 'currency' => 'JPY'],
            'Sydney, Australia' => ['baseCost' => 140, 'currency' => 'AUD']
        ];
    }

    public function getPriceRanges(?string $location = null): array
    {
        $ranges = [
            'budget' => ['min' => 30, 'max' => 80],
            'mid' => ['min' => 80, 'max' => 150],
            'luxury' => ['min' => 150, 'max' => 400]
        ];
        
        if ($location) {
            $locationData = $this->getPopularDestinations()[$location] ?? null;
            if ($locationData) {
                $baseCost = $locationData['baseCost'];
                return [
                    'budget' => ['min' => $baseCost * 0.5, 'max' => $baseCost * 0.8],
                    'mid' => ['min' => $baseCost * 0.8, 'max' => $baseCost * 1.2],
                    'luxury' => ['min' => $baseCost * 1.2, 'max' => $baseCost * 2.0]
                ];
            }
        }
        
        return $ranges;
    }

    private function getBaseCost(string $location): float
    {
        $destinations = $this->getPopularDestinations();
        return $destinations[$location]['baseCost'] ?? 100;
    }

    private function getSeasonMultiplier(?string $date): float
    {
        if (!$date) {
            return 1.0;
        }
        
        $month = (int) date('n', strtotime($date));
        
        // Peak season multipliers
        $peakMonths = [6, 7, 8, 12]; // Summer and December
        $offPeakMonths = [1, 2, 11]; // Winter
        
        if (in_array($month, $peakMonths)) {
            return 1.5;
        } elseif (in_array($month, $offPeakMonths)) {
            return 0.7;
        }
        
        return 1.0;
    }

    private function getPropertyMultiplier(string $serviceName): float
    {
        $keywords = strtolower($serviceName);
        
        if (strpos($keywords, 'luxury') !== false || strpos($keywords, 'premium') !== false) {
            return 2.0;
        } elseif (strpos($keywords, 'budget') !== false || strpos($keywords, 'cheap') !== false) {
            return 0.6;
        } elseif (strpos($keywords, 'studio') !== false || strpos($keywords, 'small') !== false) {
            return 0.8;
        } elseif (strpos($keywords, 'house') !== false || strpos($keywords, 'villa') !== false) {
            return 1.3;
        }
        
        return 1.0;
    }
}

class HotelEstimationProvider implements EstimationProviderInterface
{
    public function estimate(string $serviceName, array $parameters): array
    {
        $baseCost = $this->getBaseCost($parameters['location'] ?? 'unknown');
        $duration = $parameters['duration'] ?? 1;
        
        $starMultiplier = $this->getStarMultiplier($serviceName);
        $seasonMultiplier = $this->getSeasonMultiplier($parameters['date'] ?? null);
        
        $cost = $baseCost * $duration * $starMultiplier * $seasonMultiplier;
        
        return [
            'cost' => round($cost, 2),
            'currency' => 'USD',
            'metadata' => [
                'baseCost' => $baseCost,
                'duration' => $duration,
                'starMultiplier' => $starMultiplier,
                'seasonMultiplier' => $seasonMultiplier
            ]
        ];
    }

    public function getSuggestions(string $query): array
    {
        return [
            'Marriott Downtown',
            'Hilton Garden Inn',
            'Holiday Inn Express',
            'Four Seasons Resort',
            'Budget Inn & Suites'
        ];
    }

    public function getPopularDestinations(): array
    {
        return [
            'New York, NY' => ['baseCost' => 200, 'currency' => 'USD'],
            'Las Vegas, NV' => ['baseCost' => 120, 'currency' => 'USD'],
            'Miami, FL' => ['baseCost' => 180, 'currency' => 'USD'],
            'Paris, France' => ['baseCost' => 150, 'currency' => 'EUR'],
            'London, UK' => ['baseCost' => 180, 'currency' => 'GBP']
        ];
    }

    public function getPriceRanges(?string $location = null): array
    {
        return [
            'budget' => ['min' => 60, 'max' => 120],
            'mid' => ['min' => 120, 'max' => 250],
            'luxury' => ['min' => 250, 'max' => 600]
        ];
    }

    private function getBaseCost(string $location): float
    {
        $destinations = $this->getPopularDestinations();
        return $destinations[$location]['baseCost'] ?? 150;
    }

    private function getStarMultiplier(string $serviceName): float
    {
        $keywords = strtolower($serviceName);
        
        if (strpos($keywords, '5-star') !== false || strpos($keywords, 'luxury') !== false) {
            return 2.5;
        } elseif (strpos($keywords, '4-star') !== false || strpos($keywords, 'boutique') !== false) {
            return 1.8;
        } elseif (strpos($keywords, '3-star') !== false || strpos($keywords, 'business') !== false) {
            return 1.2;
        } elseif (strpos($keywords, '2-star') !== false || strpos($keywords, 'budget') !== false) {
            return 0.7;
        }
        
        return 1.0;
    }

    private function getSeasonMultiplier(?string $date): float
    {
        if (!$date) {
            return 1.0;
        }
        
        $month = (int) date('n', strtotime($date));
        $peakMonths = [6, 7, 8, 12];
        
        return in_array($month, $peakMonths) ? 1.3 : 1.0;
    }
}

class FlightEstimationProvider implements EstimationProviderInterface
{
    public function estimate(string $serviceName, array $parameters): array
    {
        $baseCost = $this->getBaseCost($parameters['location'] ?? 'unknown');
        $participants = $parameters['participants'] ?? 1;
        
        $classMultiplier = $this->getClassMultiplier($serviceName);
        $seasonMultiplier = $this->getSeasonMultiplier($parameters['date'] ?? null);
        
        $cost = $baseCost * $classMultiplier * $seasonMultiplier * $participants;
        
        return [
            'cost' => round($cost, 2),
            'currency' => 'USD',
            'metadata' => [
                'baseCost' => $baseCost,
                'participants' => $participants,
                'classMultiplier' => $classMultiplier,
                'seasonMultiplier' => $seasonMultiplier
            ]
        ];
    }

    public function getSuggestions(string $query): array
    {
        return [
            'American Airlines',
            'Delta Air Lines',
            'United Airlines',
            'Southwest Airlines',
            'JetBlue Airways'
        ];
    }

    public function getPopularDestinations(): array
    {
        return [
            'New York to Los Angeles' => ['baseCost' => 400, 'currency' => 'USD'],
            'New York to London' => ['baseCost' => 600, 'currency' => 'USD'],
            'Los Angeles to Tokyo' => ['baseCost' => 800, 'currency' => 'USD'],
            'Chicago to Miami' => ['baseCost' => 300, 'currency' => 'USD'],
            'San Francisco to Paris' => ['baseCost' => 700, 'currency' => 'USD']
        ];
    }

    public function getPriceRanges(?string $location = null): array
    {
        return [
            'economy' => ['min' => 200, 'max' => 600],
            'premium' => ['min' => 600, 'max' => 1200],
            'business' => ['min' => 1200, 'max' => 3000],
            'first' => ['min' => 3000, 'max' => 8000]
        ];
    }

    private function getBaseCost(string $location): float
    {
        $destinations = $this->getPopularDestinations();
        return $destinations[$location]['baseCost'] ?? 500;
    }

    private function getClassMultiplier(string $serviceName): float
    {
        $keywords = strtolower($serviceName);
        
        if (strpos($keywords, 'first') !== false) {
            return 4.0;
        } elseif (strpos($keywords, 'business') !== false) {
            return 2.5;
        } elseif (strpos($keywords, 'premium') !== false) {
            return 1.5;
        } elseif (strpos($keywords, 'economy') !== false) {
            return 1.0;
        }
        
        return 1.0;
    }

    private function getSeasonMultiplier(?string $date): float
    {
        if (!$date) {
            return 1.0;
        }
        
        $month = (int) date('n', strtotime($date));
        $peakMonths = [6, 7, 8, 12, 1];
        
        return in_array($month, $peakMonths) ? 1.4 : 1.0;
    }
}

class RestaurantEstimationProvider implements EstimationProviderInterface
{
    public function estimate(string $serviceName, array $parameters): array
    {
        $baseCost = $this->getBaseCost($parameters['location'] ?? 'unknown');
        $participants = $parameters['participants'] ?? 1;
        
        $cuisineMultiplier = $this->getCuisineMultiplier($serviceName);
        $timeMultiplier = $this->getTimeMultiplier($parameters['date'] ?? null);
        
        $cost = $baseCost * $cuisineMultiplier * $timeMultiplier * $participants;
        
        return [
            'cost' => round($cost, 2),
            'currency' => 'USD',
            'metadata' => [
                'baseCost' => $baseCost,
                'participants' => $participants,
                'cuisineMultiplier' => $cuisineMultiplier,
                'timeMultiplier' => $timeMultiplier
            ]
        ];
    }

    public function getSuggestions(string $query): array
    {
        return [
            'Italian Bistro',
            'Sushi Bar',
            'Steakhouse',
            'Mexican Cantina',
            'Thai Restaurant'
        ];
    }

    public function getPopularDestinations(): array
    {
        return [
            'New York, NY' => ['baseCost' => 60, 'currency' => 'USD'],
            'San Francisco, CA' => ['baseCost' => 70, 'currency' => 'USD'],
            'Los Angeles, CA' => ['baseCost' => 55, 'currency' => 'USD'],
            'Paris, France' => ['baseCost' => 50, 'currency' => 'EUR'],
            'Tokyo, Japan' => ['baseCost' => 40, 'currency' => 'JPY']
        ];
    }

    public function getPriceRanges(?string $location = null): array
    {
        return [
            'budget' => ['min' => 15, 'max' => 30],
            'mid' => ['min' => 30, 'max' => 60],
            'upscale' => ['min' => 60, 'max' => 150],
            'fine_dining' => ['min' => 150, 'max' => 400]
        ];
    }

    private function getBaseCost(string $location): float
    {
        $destinations = $this->getPopularDestinations();
        return $destinations[$location]['baseCost'] ?? 50;
    }

    private function getCuisineMultiplier(string $serviceName): float
    {
        $keywords = strtolower($serviceName);
        
        if (strpos($keywords, 'fine dining') !== false || strpos($keywords, 'michelin') !== false) {
            return 3.0;
        } elseif (strpos($keywords, 'upscale') !== false || strpos($keywords, 'premium') !== false) {
            return 2.0;
        } elseif (strpos($keywords, 'fast food') !== false || strpos($keywords, 'casual') !== false) {
            return 0.6;
        } elseif (strpos($keywords, 'street food') !== false || strpos($keywords, 'food truck') !== false) {
            return 0.4;
        }
        
        return 1.0;
    }

    private function getTimeMultiplier(?string $date): float
    {
        if (!$date) {
            return 1.0;
        }
        
        $hour = (int) date('H', strtotime($date));
        
        // Dinner time is more expensive
        if ($hour >= 18 && $hour <= 21) {
            return 1.3;
        } elseif ($hour >= 11 && $hour <= 14) {
            return 1.1; // Lunch
        }
        
        return 1.0; // Breakfast or late night
    }
}

class EventEstimationProvider implements EstimationProviderInterface
{
    public function estimate(string $serviceName, array $parameters): array
    {
        $baseCost = $this->getBaseCost($parameters['location'] ?? 'unknown');
        $participants = $parameters['participants'] ?? 1;
        
        $eventMultiplier = $this->getEventMultiplier($serviceName);
        $seasonMultiplier = $this->getSeasonMultiplier($parameters['date'] ?? null);
        
        $cost = $baseCost * $eventMultiplier * $seasonMultiplier * $participants;
        
        return [
            'cost' => round($cost, 2),
            'currency' => 'USD',
            'metadata' => [
                'baseCost' => $baseCost,
                'participants' => $participants,
                'eventMultiplier' => $eventMultiplier,
                'seasonMultiplier' => $seasonMultiplier
            ]
        ];
    }

    public function getSuggestions(string $query): array
    {
        return [
            'Concert Tickets',
            'Theater Show',
            'Sports Event',
            'Museum Exhibition',
            'Festival Pass'
        ];
    }

    public function getPopularDestinations(): array
    {
        return [
            'New York, NY' => ['baseCost' => 80, 'currency' => 'USD'],
            'Los Angeles, CA' => ['baseCost' => 70, 'currency' => 'USD'],
            'Las Vegas, NV' => ['baseCost' => 100, 'currency' => 'USD'],
            'London, UK' => ['baseCost' => 60, 'currency' => 'GBP'],
            'Paris, France' => ['baseCost' => 50, 'currency' => 'EUR']
        ];
    }

    public function getPriceRanges(?string $location = null): array
    {
        return [
            'budget' => ['min' => 20, 'max' => 50],
            'mid' => ['min' => 50, 'max' => 120],
            'premium' => ['min' => 120, 'max' => 300],
            'vip' => ['min' => 300, 'max' => 1000]
        ];
    }

    private function getBaseCost(string $location): float
    {
        $destinations = $this->getPopularDestinations();
        return $destinations[$location]['baseCost'] ?? 60;
    }

    private function getEventMultiplier(string $serviceName): float
    {
        $keywords = strtolower($serviceName);
        
        if (strpos($keywords, 'vip') !== false || strpos($keywords, 'premium') !== false) {
            return 3.0;
        } elseif (strpos($keywords, 'concert') !== false || strpos($keywords, 'stadium') !== false) {
            return 1.5;
        } elseif (strpos($keywords, 'theater') !== false || strpos($keywords, 'broadway') !== false) {
            return 2.0;
        } elseif (strpos($keywords, 'museum') !== false || strpos($keywords, 'exhibition') !== false) {
            return 0.8;
        } elseif (strpos($keywords, 'festival') !== false) {
            return 1.2;
        }
        
        return 1.0;
    }

    private function getSeasonMultiplier(?string $date): float
    {
        if (!$date) {
            return 1.0;
        }
        
        $month = (int) date('n', strtotime($date));
        $peakMonths = [6, 7, 8, 12]; // Summer and December
        
        return in_array($month, $peakMonths) ? 1.3 : 1.0;
    }
}

class TransportEstimationProvider implements EstimationProviderInterface
{
    public function estimate(string $serviceName, array $parameters): array
    {
        $baseCost = $this->getBaseCost($parameters['location'] ?? 'unknown');
        $duration = $parameters['duration'] ?? 1;
        $participants = $parameters['participants'] ?? 1;
        
        $transportMultiplier = $this->getTransportMultiplier($serviceName);
        
        $cost = $baseCost * $duration * $transportMultiplier * $participants;
        
        return [
            'cost' => round($cost, 2),
            'currency' => 'USD',
            'metadata' => [
                'baseCost' => $baseCost,
                'duration' => $duration,
                'participants' => $participants,
                'transportMultiplier' => $transportMultiplier
            ]
        ];
    }

    public function getSuggestions(string $query): array
    {
        return [
            'Uber/Lyft',
            'Taxi',
            'Rental Car',
            'Public Transit',
            'Bike Share'
        ];
    }

    public function getPopularDestinations(): array
    {
        return [
            'New York, NY' => ['baseCost' => 25, 'currency' => 'USD'],
            'San Francisco, CA' => ['baseCost' => 30, 'currency' => 'USD'],
            'Los Angeles, CA' => ['baseCost' => 35, 'currency' => 'USD'],
            'London, UK' => ['baseCost' => 20, 'currency' => 'GBP'],
            'Paris, France' => ['baseCost' => 15, 'currency' => 'EUR']
        ];
    }

    public function getPriceRanges(?string $location = null): array
    {
        return [
            'public' => ['min' => 5, 'max' => 15],
            'rideshare' => ['min' => 15, 'max' => 40],
            'taxi' => ['min' => 20, 'max' => 60],
            'rental' => ['min' => 50, 'max' => 150]
        ];
    }

    private function getBaseCost(string $location): float
    {
        $destinations = $this->getPopularDestinations();
        return $destinations[$location]['baseCost'] ?? 25;
    }

    private function getTransportMultiplier(string $serviceName): float
    {
        $keywords = strtolower($serviceName);
        
        if (strpos($keywords, 'rental') !== false || strpos($keywords, 'car') !== false) {
            return 2.0;
        } elseif (strpos($keywords, 'taxi') !== false) {
            return 1.5;
        } elseif (strpos($keywords, 'uber') !== false || strpos($keywords, 'lyft') !== false) {
            return 1.2;
        } elseif (strpos($keywords, 'public') !== false || strpos($keywords, 'transit') !== false) {
            return 0.3;
        } elseif (strpos($keywords, 'bike') !== false) {
            return 0.1;
        }
        
        return 1.0;
    }
}
