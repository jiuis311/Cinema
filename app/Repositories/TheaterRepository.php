<?php

namespace App\Repositories;

use App\Repositories\Support\SAbstractRepository;
use App\Theater;

class TheaterRepository extends SAbstractRepository
{

    const SORT_BY_ARR = ['DESC', 'ASC'];
    const ORDER_BY = 'id';

    /**
     * Define primary model in this repository.
     * @return string
     */
    public function model()
    {
        return 'App\Theater';
    }

    /**
     * Rules create.
     * @return array
     */
    public function rulesCreate()
    {
        return [
            'name' => 'required',
            'email' => 'required|email|unique:theaters,email,NULL,id,deleted_at,NULL',
            'password' => 'required|min:4',
            'avatar' => 'max:4096|mimes:png,jpg,jpeg,gif'
        ];
    }

    /**
     * Rules update.
     * @return array
     */
    public function rulesUpdate($id)
    {
        $rules = $this->rulesCreate();
        $rules['email'] = "required|email|unique:theaters,email,$id,id,deleted_at,NULL";
        $rules['password'] = 'min:4';
        return $rules;
    }

    /**
     * Find a theater
     * @param int $theaterId
     * @return Theater
     */
    public function find($theaterId)
    {
        return Theater::find($theaterId);
    }

    /**
     * Update a theater.
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return bool
     */
    public function update($request, $id)
    {
        $theater = Theater::find($id);
        $theater->name = $request->get('name');
        $theater->email = $request->get('email');
        if (!empty($request->get('password'))) {
            $theater->password = bcrypt($request->get('password'));
        }
        if (!is_null($request->get('active'))) {
            $theater->active = Theater::ACTIVE;
        } else {
            $theater->active = Theater::INACTIVE;
        }
        $avatar = $request->file('avatar');
        if (isset($avatar)) {
            $upload = $avatar->getClientOriginalName();
            $filename = str_slug(pathinfo($upload, PATHINFO_FILENAME));
            $fileExtension = str_slug(pathinfo($upload, PATHINFO_EXTENSION));
            $changeName = time() . '_' . $filename . '.' . $fileExtension;
            $avatar->move(Theater::PATH_AVATAR, $changeName);
            $avatarPath = Theater::PATH_AVATAR . $changeName;
            $theater->avatar = $avatarPath;
        }
        $theater->save();
        
        return $theater;
    }

    /**
     * Create a theater.
     * @param \Illuminate\Http\Request $request
     * @return Theater
     */
    public function create($request)
    {
        $active = is_null($request->get('active')) ? Theater::INACTIVE : Theater::ACTIVE;
        $theater = Theater::create([
                    'name' => $request->get('name'),
                    'email' => $request->get('email'),
                    'password' => bcrypt($request->get('password')),
                    'role_id' => $request->get('role_id'),
                    'active' => $active
        ]);
        $avatar = $request->file('avatar');
        if (isset($avatar)) {
            $upload = $avatar->getClientOriginalName();
            $filename = str_slug(pathinfo($upload, PATHINFO_FILENAME));
            $fileExtension = str_slug(pathinfo($upload, PATHINFO_EXTENSION));
            $changeName = time() . '_' . $filename . '.' . $fileExtension;
            $avatar->move(Theater::PATH_AVATAR, $changeName);
            $avatarPath = Theater::PATH_AVATAR . $changeName;
            $theater->avatar = $avatarPath;
            $theater->save();
        }
        return $theater;
    }

    /**
     * Delete a theater.
     * @param int $id
     */
    public function delete($id)
    {
        $theater = $this->find($id);
        $theater->delete();
    }
    
    /**
     * Count theater
     * @return type
     */
    public function count(){
        return $this->model->where('active',Theater::ACTIVE)->count();
    }

    public function getActiveList() {
        return $this->model->where('status','=',1)->get();
    }
}
