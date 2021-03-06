<?php namespace App\Http\Controllers;

use App\Applicant_Course;
use App\Applicant_offer;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Course;
use App\Applicant;
use App\Phase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Input;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Laracasts\Flash\Flash;
use Swift_SmtpTransport;
use Swift_Mailer;



class AdminController extends Controller
{

    protected $check;


    /**
     * rank constructor: checks auth and role middleware
     */
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role');
        $this->middleware('time', ['only' => ['rank', 'rankShow', 'submit', 'save', 'sendOffers', 'sendEmail']]);
    }

    /**
     * home page view
     *
     * @return \Illuminate\View\View
     */
    public function home()
    {
        $phase = Phase::getPhaseData();
        if ($phase == null) {
            return redirect('admin/settings');
        }
        return view('admin/home', Phase::getPhaseText());
    }

    public function about()
    {
        return view('admin/about');
    }

    /**
     * forwards to rank home page
     *
     * @return \Illuminate\View\View
     */
    public function rank()
    {
        $courses = Course::all()->toArray();

        return view('admin/rank_home', ['courses' => $courses]);
    }


    /**
     * courseid rank page
     *
     * @param $id
     * @return \Illuminate\View\View
     */
    public function rankShow($id)
    {
        if (Course::checkIfCourseComplete($id)) {
            Flash::info('Course ' . $id . ' rank already submitted!');
            return redirect()->back();
        }
        if (!Applicant_Course::checkIfCourseHasApps($id)) {
            Flash::info('Course ' . $id . ' does not have any applicants!');
            return redirect()->back();
        }

        $courses = Course::all()->toArray();

        $db = new DB();
        $applied = $db::table('users')
            ->join('applicantcourse', 'users.sso', '=', 'applicantcourse.sso')
            ->join('applicant', 'users.sso', '=', 'applicant.sso')
            ->where('applicantcourse.action', '=', '001')
            ->where('applicantcourse.courseid', '=', $id)
            ->select('*')
            ->orderBy('applicantcourse.rank', 'asc')
            ->orderBy('applicantcourse.recommendation', 'asc')
            ->orderBy('applicant.gpa', 'desc')
            ->get();

        return view('admin/rank_course', ['courses' => $courses, 'applied' => $applied, 'cid' => $id]);
    }


    /**
     * function to submit rank and create offer field
     * called on submit button click
     *
     * @param $id
     * @return \Illuminate\View\View
     */
    public function submit($id)
    {
        $offers = Applicant_Course::where('courseid', '=', $id)
            ->where('action', '=', '001')
            ->orderBy('rank', 'asc')
            ->get();

        $count = 1;
        foreach ($offers as $offer) {
            Applicant_offer::create([
                'sso' => $offer->sso,
                'courseid' => $offer->courseid,
                'rank' => $count,
                'offersent' => false,
                'offeraccepted' => false
            ]);
            $count++;
        }

        Flash::success('Rank Submitted for ' . $id . '!');
        return redirect('admin/rank');
    }

    /**
     * function to update rank for course
     * called whenever order is changed
     */
    public function save()
    {
        $input = Request::all();

        $ids = $input['ids'];
        $courseid = $input['courseid'];

        $count = 1;
        foreach ($ids as $id) {
            Applicant_Course::where('courseid', '=', $courseid)
                ->where('sso', '=', $id)
                ->where('action', '=', '001')
                ->update(['rank' => $count]);
            $count++;
        }
    }

    public function settings()
    {
        return view('admin/settings', Phase::getPhaseText());
    }


    public function phaseStore()
    {
        $input = Request::all();
        $input['author'] = \Auth::user()->sso;

        $phase = Phase::create($input);

        $phase->save();

        Flash::success('Settings were updated!');
        return view('admin/settings', Phase::getPhaseText());

    }

    public function sendOffers()
    {
        $topTen = new Applicant_offer();
        $topTen = $topTen->getTopTenApplicantsForEachCourse();

        return view('admin/offer', ['topTen' => $topTen]);
    }

    public function sendEmail()
    {
        if (Session::token() !== Input::get('_token')) {
            return Response::json(array(
                'msg' => 'Unauthorized attempt to create setting'
            ));
        }
        $appOffer = new Applicant_offer();
        $email = Input::get('email');
        $course = Input::get('course');
        $sso = Input::get('sso');

        $email = htmlspecialchars($email);
        $email = pg_escape_string($email);
        $course = htmlspecialchars($course);
        $course = pg_escape_string($course);
        $sso = htmlspecialchars($sso);
        $sso = pg_escape_string($sso);

        $applicant = array(
            'sso' => $sso,
            'course' => $course,
        );

        if (isset($email)) {

            $transport = Swift_SmtpTransport::newInstance('smtp.gmail.com', 465, "ssl")
                ->setUsername('tapplemizzou@gmail.com')
                ->setPassword('Riley123..');

            $mailer = Swift_Mailer::newInstance($transport);

            $message = \Swift_Message::newInstance('TA Job Offer!')
                ->setFrom(array('tapplemizzou@gmail.com'))
                ->setTo(array($email))
                ->setBody("Congradulations, You have been offered a TA position for ".$course."!");

            $result = $mailer->send($message);

            $appOffer->updateOfferSentBySso($applicant);

            $response = array(
                'status' => 'success',
                'msg' => 'Email was sent successfully',
            );
        } else {
            $response = array(
                'status' => 'failure',
                'msg' => 'Email was sent unsuccessfully',
            );
        }

        return Response::json($response);
    }
}
