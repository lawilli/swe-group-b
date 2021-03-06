<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateApplicantofferTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('applicantoffer', function(Blueprint $table)
		{
			$table->increments('id');
			$table->timestamps();#Event
			$table->string('sso', 20);#SSO String
			$table->string('courseid', 10);#CS 4320
			$table->integer('rank');
            $table->boolean('offersent')->default(false);
            $table->boolean('offeraccepted')->default(false);
			$table->foreign('sso')->references('sso')->on('applicant')->onDelete('cascade');#If Applicant DNE, then Applicant Offers should be removed
			$table->foreign('courseid')->references('courseid')->on('course')->onDelete('cascade');#If Course DNE, then Applicant Offers should be removed
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::dropIfExists('applicantoffer');
	}

}
