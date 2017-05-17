<?php

namespace Task4ItAPI\Http\Controllers;

use Validator;

class TagController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
       $tags = \Task4ItAPI\Tag::get();

       return $this->response->collection(
           $tags,
           new \Task4ItAPI\Http\Transformers\TagTransformer
       );
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store()
    {
        $validator = Validator::make($request, array(
            'tag' => 'min:2|required|unique:tags',
        ));

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            $errors = array_map(
                function ($error) {
                    return trans($error);
                },
                $errors
            );

            \Log::info("REGISTER:: NOT validating tag");

            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException(
                'Could not validate data for tag creation', $this->translateErrors($validator->errors()->all())
            );
        }

        $tag= new \Task4ItAPI\Tag();
        $tag->tag = $request->input('tag');

        $tag->save();

        return $this->response->item($tag, new \Task4ItAPI\Http\Transformers\TagTransformer);
    }

    /**
     * Display the specified resource.
     *
     * @param  int      $id
     * @return Response
     */
    public function show($id)
    {
        $tag = \Task4ItAPI\Tag::find($id);

        if (!$tag) {
            return $this->response->errorNotFound('Could not find tag ' . $id);
        }

        return $this->response->item($tag, new \Task4ItAPI\Http\Transformers\TagTransformer);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int      $id
     * @return Response
     */
    public function update($id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int      $id
     * @return Response
     */
    public function destroy($id)
    {
        //
    }
}
