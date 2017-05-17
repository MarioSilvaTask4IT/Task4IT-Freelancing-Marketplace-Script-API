<?php

namespace Task4ItAPI\Http\Controllers;

class OfferController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $offers = \Task4ItAPI\Offer::all();

        return $this->response->collection($offers, new \Task4ItAPI\Http\Transformers\OfferTransformer);
    }
   /**
     * Display the specified resource.
     *
     * @param  int      $id
     * @return Response
     */
    public function show($offerId)
    {
        $offer = \Task4ItAPI\Offer::find($offerId);

        if (!$offer) {
            return $this->response->errorNotFound('Could not find offer ' . $offerId);
        }

        return $this->response->item($offer, new \Task4ItAPI\Http\Transformers\OfferTransformer);
    }
}
