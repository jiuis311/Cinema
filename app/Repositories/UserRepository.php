<?php

namespace App\Repositories;

use App\User;
use App\Repositories\Support\SAbstractRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Requests;
use Illuminate\Support\Facades\DB;

class UserRepository extends SAbstractRepository
{

    const SORT_BY_ARR = ['DESC', 'ASC'];
    const ORDER_BY = 'id';

    /**
     * Define primary model in this repository.
     * @return string
     */
    public function model()
    {
        return 'App\User';
    }

    /**
     * Rules create.
     * @return array
     */
    public function rulesCreate()
    {
        return [
            'name' => 'required',
            'email' => 'required|email|unique:users,email,NULL,id,deleted_at,NULL',
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
        $rules['email'] = "required|email|unique:users,email,$id,id,deleted_at,NULL";
        $rules['password'] = 'min:4';
        return $rules;
    }

    /**
     * Get all roles array.
     * @return type
     */
    public function roleArr()
    {
        return [
            User::ROLE_ADMIN => 'ADMIN',
            User::ROLE_USER => 'USER'
        ];
    }

    /**
     * Find a user
     * @param int $userId
     * @return User
     */
    public function find($userId)
    {
        return User::find($userId);
    }

    /**
     * Get all user with role = ADMIN
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Pagination\Paginator
     */
    public function roleAdmin($request)
    {
        $query = $this->all($request, null);
        return $query->where('role_id', '=', User::ROLE_ADMIN)->paginate(self::PAGE_SIZE);
    }

    /**
     * Get all user with role = USER
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Pagination\Paginator
     */
    public function roleUser($request)
    {
        $query = $this->all($request, null);
        return $query->where('role_id', '=', User::ROLE_USER)->paginate(self::PAGE_SIZE);
    }

    /**
     * Update a user.
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return bool
     */
    public function update($request, $id)
    {
        $user = User::find($id);
        $user->name = $request->get('name');
        $user->email = $request->get('email');
        if (!empty($request->get('password'))) {
            $user->password = bcrypt($request->get('password'));
        }
        if (!is_null($request->get('active'))) {
            $user->active = User::ACTIVE;
        } else {
            $user->active = User::INACTIVE;
        }
        $user->role_id = $request->get('role_id');
        if ($user->id == User::CAN_NOT_DELETE) {
            $user->active = User::ACTIVE;
            $user->role_id = User::ROLE_ADMIN;
        }
        $avatar = $request->file('avatar');
        if (isset($avatar)) {
            $upload = $avatar->getClientOriginalName();
            $filename = str_slug(pathinfo($upload, PATHINFO_FILENAME));
            $fileExtension = str_slug(pathinfo($upload, PATHINFO_EXTENSION));
            $changeName = time() . '_' . $filename . '.' . $fileExtension;
            $avatar->move(User::PATH_AVATAR, $changeName);
            $avatarPath = User::PATH_AVATAR . $changeName;
            $user->avatar = $avatarPath;
        }
        $user->save();
        
        return $user;
    }

    /**
     * Create a user.
     * @param \Illuminate\Http\Request $request
     * @return User
     */
    public function create($request)
    {
        $active = is_null($request->get('active')) ? User::INACTIVE : User::ACTIVE;
        $user = User::create([
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
            $avatar->move(User::PATH_AVATAR, $changeName);
            $avatarPath = User::PATH_AVATAR . $changeName;
            $user->avatar = $avatarPath;
            $user->save();
        }
        return $user;
    }

    /**
     * Delete a user.
     * @param int $id
     */
    public function delete($id)
    {
        $user = $this->find($id);
        $user->delete();
    }
    
    /**
     * Count user
     * @return type
     */
    public function count(){
        return $this->model->where('active',User::ACTIVE)->count();
    }

    public function getOrderedTicketList() {
        $movies = DB::select('SELECT schedules.id, movies.title, theaters.name, schedules.type, schedules.show_date, schedules.show_time '
                        . 'FROM schedules '
                        . 'INNER JOIN movies ON schedules.movie_id = movies.id '
                        . 'INNER JOIN theaters ON schedules.theater_id = theaters.id '
                        . 'INNER JOIN tickets ON schedules.id = tickets.schedule_id '
                        . 'WHERE tickets.user_id = ? '
                        . 'AND show_date >= ? '
                        . 'GROUP BY schedules.id, movies.title, theaters.name, schedules.show_date, schedules.show_time', [Auth::id(), config('constant.today')]);
        foreach ($movies as $key => $value) {
            $tickets = DB::select('SELECT chair_num '
                            . 'FROM tickets '
                            . 'WHERE user_id = ? '
                            . 'AND schedule_id = ? ', [Auth::id(), $value->id]);
            $value->tickets = $tickets;
            $movies[$key] = $value;
        }
        return $movies;
    }

    public function like($movieId) {
        \App\Like::insert(['movie_id' => $movieId, 'user_id' => Auth::id()]);
        $movie = \App\Movie::find($movieId);
        $movie->like_num = $movie->like_num + 1;
        $movie->save();
    }
    
    public function unlike($movieId) {
        \App\Like::where('movie_id', '=', $movieId)
                ->where('user_id', '=', Auth::id())->delete();
        $movie = \App\Movie::find($movieId);
        $movie->like_num = $movie->like_num - 1;
        $movie->save();
    }
    
    public function getBill(Request $request) {
        $seatList = $request->seat_list;
        $scheduleId = $request->schedule_id;
        foreach ($seatList as $seat) {
            //$exist = \DB::table('tickets')->where('schedule_id', $scheduleId)->where('chair_num', $seat);
            $exist = DB::select('SELECT id FROM tickets WHERE schedule_id = ? AND chair_num = ?', [$scheduleId, $seat]);
            if (!$exist) {
                //DB::insert('INSERT INTO tickets(schedule_id, user_id, chair_num) VALUES (?,?,?)', [$scheduleId, Auth::id(), $seat]);
                DB::table('tickets')->insert(
                        ['schedule_id' => $scheduleId, 'user_id' => Auth::id(), 'chair_num' => $seat]
                );
                $user = \App\User::find(Auth::id());
                $schedule = \DB::table('schedules')->find($scheduleId);
                $tmp = (int) $user->total_amount + (int) $schedule->price;
                \App\User::where('id', Auth::id())->update(['total_amount' => $tmp]);
                if ($tmp >= 1000000) {
                    \App\User::find(Auth::id())->update(['account_type' => 'vip']);
                } else {
                    \App\User::find(Auth::id())->update(['account_type' => 'normal']);
                }
            }
        }
    }

    public function getSeatMap($schedule_id) {
        $seatmap = DB::select('SELECT movies.*, theaters.*, schedules.* '
                        . 'FROM schedules '
                        . 'INNER JOIN movies ON schedules.movie_id = movies.id '
                        . 'INNER JOIN theaters ON schedules.theater_id = theaters.id '
                        . 'WHERE schedules.id = ?', [$schedule_id]);
        return $seatmap;
    }

}